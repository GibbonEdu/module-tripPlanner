<?php

use Gibbon\Comms\NotificationEvent;
use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\Messenger\GroupGateway;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\TripPlanner\Domain\ApproverGateway;
use Gibbon\Module\TripPlanner\Domain\TripCostGateway;
use Gibbon\Module\TripPlanner\Domain\TripDayGateway;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Module\TripPlanner\Domain\TripLogGateway;
use Gibbon\Module\TripPlanner\Domain\TripPersonGateway;
use Gibbon\Services\Format;

require_once '../../gibbon.php';
require_once './moduleFunctions.php';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module');

//Checking if editing mode should be enabled
$edit = false;

$mode = $_REQUEST['mode'] ?? '';
$saveMode = $_REQUEST['saveMode'] ?? 'Submit';
$tripPlannerRequestID = $_REQUEST['tripPlannerRequestID'] ?? '';

$tripGateway = $container->get(TripGateway::class);

//Check if a mode and id are given
if (!empty($mode) && !empty($tripPlannerRequestID)) {
    //Get trip from gateway
    $trip = $tripGateway->getByID($tripPlannerRequestID);    

    //If the trip exists, set to edit mode
    if (!empty($trip)) {
        $edit = true;
    }
}

$isDraft = !empty($trip) && $trip['status'] == 'Draft';

$gibbonPersonID = $session->get('gibbonPersonID');
$personName = Format::name('', $session->get('preferredName'), $session->get('surname'), 'Staff', false, true);

$highestAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_submitRequest.php') || ($edit && $highestAction != 'Manage Trips_full' && $trip['creatorPersonID'] != $gibbonPersonID)) {
    //If the action isn't accesible, or in edit mode and the current user isn't the owner, throw error.
    $URL .= '/trips_manage.php&return=error0';
    header("Location: {$URL}");
    exit;
} else if ((isset($trip) && empty($trip)) || (!empty($mode) && !$edit)) {
    //If a trip is provided, but doesn't exit, Or the mode is set, but edit isn't enabled, throw error.
    $URL .= '/trips_submitRequest.php&return=error1&reason=a';
    header("Location: {$URL}");
    exit;
} else {
    $URL .= '/trips_submitRequest.php&tripPlannerRequestID='.$tripPlannerRequestID.'&mode='.$mode;

    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    $settingGateway = $container->get(SettingGateway::class);

    $riskAssessmentApproval = $settingGateway->getSettingByScope('Trip Planner', 'riskAssessmentApproval');

    $partialFail = false;
    $returnCode = '';

    //Load Trip Data
    //Format: Key => Required Flag
    $tripData = [
        'title'             => true,
        'description'       => true,
        'location'          => true,
        'riskAssessment'    => !$riskAssessmentApproval,
        'letterToParents'   => false,
        'deepLearningSync'   => false,
    ];

    foreach ($tripData as $key => $required) {
        if (!empty($_POST[$key])) {
            $tripData[$key] = $_POST[$key];
        } else if ($required) {
            $partialFail = true;
            $returnCode = 'warning3';
        }
    }

    if ($mode != 'edit') {
        $tripData['creatorPersonID'] = $gibbonPersonID;
        $tripData['gibbonSchoolYearID'] = $gibbonSchoolYearID;
    }

    if ($saveMode == 'Draft' && (empty($trip) || $isDraft)) {
        $tripData['status'] = 'Draft';
    } elseif ($saveMode != 'Draft' && $isDraft) {
        $tripData['status'] = 'Requested';
    }
    
    //Load Trip People
    $tripPeople = [];

    $teachers = $_POST['teachers'] ?? [];
    foreach ($teachers as $person) {
        $tripPeople[] = ['role' => 'Teacher', 'gibbonPersonID' => $person];
    }

    //If no teachers have been added, throw an error.
    if (empty($tripPeople)) {
        $partialFail = true;
        $returnCode = 'warning6';
    } 

    $students = $_POST['students'] ?? [];
    foreach ($students as $person) {          
        $tripPeople[] = ['role' => 'Student', 'gibbonPersonID' => $person];
    }

    //Load Trip Days
    $tripDays = [];
    $dateIDs = [];
    $dateTimeOrder = $_POST['dateTimeOrder'] ?? [];

    foreach ($dateTimeOrder as $order) {
        $day = $_POST['dateTime'][$order] ?? [];
        if (!is_array($day)) {
            continue;
        }

        $tripPlannerRequestDaysID = $day['tripPlannerRequestDaysID'] ?? '';
        if (!empty($tripPlannerRequestDaysID)) {
            $dateIDs[] = str_pad($tripPlannerRequestDaysID, 10, '0', STR_PAD_LEFT);
        }

        if (empty($day['startDate']) || empty($day['endDate'])) {
            $partialFail = true;
            $returnCode = 'warning7';
            continue;
        }

        if ($day['startDate'] > $day['endDate']) {
            $endDate = $day['endDate'];
            $day['endDate'] = $day['startDate'];
            $day['startDate'] = $endDate;
        }

        if (!empty($day['startTime']) && !empty($day['endTime'])) {
            $day['allDay'] = '0';

            $startTime = DateTime::createFromFormat('H:i', $day['startTime']) ?: DateTime::createFromFormat('H:i:s', $day['startTime']);
            $endTime = DateTime::createFromFormat('H:i', $day['endTime']) ?: DateTime::createFromFormat('H:i:s', $day['endTime']);

            if ($startTime && $endTime && $endTime <= $startTime) {
                $swapTime = $day['startTime'];
                $day['startTime'] = $day['endTime'];
                $day['endTime'] = $swapTime;
            }
        } else {
            $day['allDay'] = '1';
            $day['startTime'] = '00:00:00';
            $day['endTime'] = '00:00:00';
        }

        $tripDays[] = [
            'tripPlannerRequestDaysID' => $tripPlannerRequestDaysID,
            'startDate'                => $day['startDate'],
            'endDate'                  => $day['endDate'],
            'allDay'                   => $day['allDay'],
            'startTime'                => $day['startTime'],
            'endTime'                  => $day['endTime'],
        ];
    }

    if (empty($tripDays) && empty($dateIDs)) {
        $partialFail = true;
        $returnCode = 'warning4';
    }

    //TODO: DateTime overlap validation 

    //Load Trip Costs
    $tripCosts = [];
    $costIDs = [];
    $costOrder = $_POST['costOrder'] ?? [];

    foreach ($costOrder as $order) {
        $cost = $_POST['cost'][$order] ?? [];
        if (!is_array($cost)) {
            continue;
        }

        $tripPlannerCostBreakdownID = $cost['tripPlannerCostBreakdownID'] ?? '';
        if (!empty($tripPlannerCostBreakdownID)) {
            $costIDs[] = str_pad($tripPlannerCostBreakdownID, 10, '0', STR_PAD_LEFT);
        }

        if (empty($cost['title']) || empty($cost['cost']) || $cost['cost'] < 0) {
            $partialFail = true;
            $returnCode = 'warning5';
            continue;
        }

        $tripCosts[] = [
            'tripPlannerCostBreakdownID' => $tripPlannerCostBreakdownID,
            'title'                      => $cost['title'],
            'description'                => $cost['description'] ?? '',
            'cost'                       => $cost['cost'],
        ];
    }

    //Begin Transaction
    $tripGateway->beginTransaction();

    //Insert Trip Data
    if ($edit) {
        if (!$tripGateway->update($tripPlannerRequestID, $tripData)) {
            $tripPlannerRequestID = null;
        }
    } else {
        $tripPlannerRequestID = $tripGateway->insert($tripData);
    }

    //If no Trip Planner Request, rollback and return error
    if (empty($tripPlannerRequestID)) {
        $tripGateway->rollBack();
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    } 

    //TODO: Check if success, rollback if not

    //Insert new Trip People data and remove old data (if exists).
    $tripPersonGateway = $container->get(TripPersonGateway::class);
    $tripPersonGateway->deleteWhere(['tripPlannerRequestID' => $tripPlannerRequestID]);
    $tripPersonGateway->bulkInsert($tripPlannerRequestID, $tripPeople);

    // Add or edit costs by ID; only remove blocks the user deleted
    $tripCostGateway = $container->get(TripCostGateway::class);
    if (!empty($costOrder) || ($_POST['costCount'] ?? '') === '0') {
        foreach ($tripCosts as $cost) {
            $data = [
                'tripPlannerRequestID' => $tripPlannerRequestID,
                'title'                => $cost['title'],
                'description'          => $cost['description'],
                'cost'                 => $cost['cost'],
            ];

            $tripPlannerCostBreakdownID = $cost['tripPlannerCostBreakdownID'] ?? '';
            if (!empty($tripPlannerCostBreakdownID)) {
                $tripCostGateway->update($tripPlannerCostBreakdownID, $data);
            } else {
                $tripPlannerCostBreakdownID = $tripCostGateway->insert($data);
                if (!empty($tripPlannerCostBreakdownID)) {
                    $costIDs[] = str_pad($tripPlannerCostBreakdownID, 10, '0', STR_PAD_LEFT);
                } else {
                    $partialFail = true;
                    $returnCode = 'warning5';
                }
            }
        }

        $tripCostGateway->deleteCostsNotInList($tripPlannerRequestID, $costIDs);
    }

    // Add or edit days by ID; only remove blocks the user deleted
    $tripDayGateway = $container->get(TripDayGateway::class);
    if (!empty($dateTimeOrder) || ($_POST['dateTimeCount'] ?? '') === '0') {
        foreach ($tripDays as $day) {
            $data = [
                'tripPlannerRequestID' => $tripPlannerRequestID,
                'startDate'            => $day['startDate'],
                'endDate'              => $day['endDate'],
                'allDay'               => $day['allDay'],
                'startTime'            => $day['startTime'],
                'endTime'              => $day['endTime'],
            ];

            $tripPlannerRequestDaysID = $day['tripPlannerRequestDaysID'] ?? '';
            if (!empty($tripPlannerRequestDaysID)) {
                $tripDayGateway->update($tripPlannerRequestDaysID, $data);
            } else {
                $tripPlannerRequestDaysID = $tripDayGateway->insert($data);
                if (!empty($tripPlannerRequestDaysID)) {
                    $dateIDs[] = str_pad($tripPlannerRequestDaysID, 10, '0', STR_PAD_LEFT);
                } else {
                    $partialFail = true;
                    $returnCode = 'warning4';
                }
            }
        }

        $tripDayGateway->deleteDatesNotInList($tripPlannerRequestID, $dateIDs);
    }

    $groupGateway = $container->get(GroupGateway::class);
    $createGroup = $_POST['createGroup'] ?? 'N' ;

    //Clear Group Data
    if ($edit) {
        if ($groupGateway->exists($trip['messengerGroupID'])) {
            $groupID = $trip['messengerGroupID'];
            $groupGateway->deletePeopleByGroupID($groupID);
        }
    }
    
    if (empty($trip['messengerGroupID']) && $createGroup == 'Y') {
        $groupID = $groupGateway->insertGroup([
                'gibbonPersonIDOwner' => $gibbonPersonID,
                'gibbonSchoolYearID' => $gibbonSchoolYearID,
                'name' => $tripData['title'] . ' (Trip Planner)'
        ]);
        
        $tripGateway->update($tripPlannerRequestID, ['messengerGroupID' => $groupID]);
    }

    if (!empty($groupID)) {
        foreach ($tripPeople as $person) {
            $groupGateway->insertGroupPerson([
                'gibbonGroupID' => $groupID,
                'gibbonPersonID' => $person['gibbonPersonID']
            ]);
        }
    }

    if ($saveMode != 'Draft') {
        $tripLogGateway = $container->get(TripLogGateway::class);
        $tripLogGateway->insert([
            'tripPlannerRequestID' => $tripPlannerRequestID,
            'gibbonPersonID'       => $gibbonPersonID,
            'comment'              => $_POST['changeSummary'] ?? '',
            'action'               => $edit && !$isDraft ? 'Edit' : 'Request'
        ]);
    }

    $tripGateway->commit();

    $notificationGateway = $container->get(NotificationGateway::class);
    $notificationSender = new NotificationSender($notificationGateway, $session);

    if ($saveMode != 'Draft' && ($isDraft || !$edit)) {
        $event = new NotificationEvent('Trip Planner', 'New Trip Request');

        $event->setNotificationText(__('{person} has submitted a new Trip Request: {trip}', ['person' => $personName, 'trip' => $tripData['title']]));
        $event->setActionLink('/index.php?q=/modules/Trip Planner/trips_requestApprove.php&tripPlannerRequestID=' . $tripPlannerRequestID);

        $requestApprovalType = $settingGateway->getSettingByScope('Trip Planner', 'requestApprovalType');
        $approverGateway = $container->get(ApproverGateway::class);

        if ($requestApprovalType == 'Chain Of All') {
            $firstApprover = $approverGateway->selectNextApprover($tripPlannerRequestID);
            if ($firstApprover->isNotEmpty()) {
                $event->addRecipient($firstApprover->fetch()['gibbonPersonID']);
            }
        } else {
            $approverCriteria = $approverGateway->newQueryCriteria();
            $approvers = $approverGateway->queryApprovers($approverCriteria);
            foreach ($approvers as $approver) {
                $event->addRecipient($approver['gibbonPersonID']);
            }
        }

        //Send all notifications
        $event->pushNotifications($notificationGateway, $notificationSender);

        // Add a notification for the trip owner
        $notificationSender->addNotification($gibbonPersonID, __('You have submitted a new Trip Request (pending approval): {trip}', ['trip' => $tripData['title']]), 'Trip Planner', '/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID=' . $tripPlannerRequestID);

        $notificationSender->sendNotifications();
    }

    if ($saveMode != 'Draft' && $edit && !$isDraft && !empty($_POST['changeSummary'])) {
        tripCommentNotifications($tripPlannerRequestID, $gibbonPersonID, $personName, $tripLogGateway, $tripData, $_POST['changeSummary'], $notificationSender);

        $notificationSender->sendNotifications();
    }

    if ($partialFail) {
        $URL .= '&return='.$returnCode.'&tripPlannerRequestID=' . $tripPlannerRequestID . ($edit || $saveMode == 'Draft' ? '&mode=edit' : '');
        header("Location: {$URL}");
        exit;
    }

    $URL .= '&return=success0&tripPlannerRequestID=' . $tripPlannerRequestID . ($edit || $saveMode == 'Draft' ? '&mode=edit' : '');
    header("Location: {$URL}");
    exit;
}
