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

$URL = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/' . $gibbon->session->get('module');

//Checking if editing mode should be enabled
$edit = false;

$mode = $_POST['mode'] ?? '';
$tripPlannerRequestID = $_POST['tripPlannerRequestID'] ?? '';

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

$gibbonPersonID = $gibbon->session->get('gibbonPersonID');

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_submitRequest.php') || ($edit && $trip['creatorPersonID'] != $gibbonPersonID)) {
    //If the action isn't accesible, or in edit mode and the current user isn't the owner, throw error.
    $URL .= '/trips_manage.php&return=error0';
    header("Location: {$URL}");
    exit();
} else if ((isset($trip) && empty($trip)) || (!empty($mode) && !$edit)) {
    //If a trip is provided, but doesn't exit, Or the mode is set, but edit isn't enabled, throw error.
    $URL .= '/trips_submitRequest.php&return=error1';
    header("Location: {$URL}");
    exit();
} else {
    $URL .= '/trips_submitRequest.php';

    $gibbonSchoolYearID = $gibbon->session->get('gibbonSchoolYearID');

    $settingGateway = $container->get(SettingGateway::class);

    $riskAssessmentApproval = $settingGateway->getSettingByScope('Trip Planner', 'riskAssessmentApproval');

    //Load Trip Data
    //Format: Key => Required Flag
    $tripData = [
        'title'             => true,
        'description'       => true,
        'location'          => true,
        'riskAssessment'    => !$riskAssessmentApproval,
        'letterToParents'   => false
    ];

    foreach ($tripData as $key => $required) {
        if (!empty($_POST[$key])) {
            $tripData[$key] = $_POST[$key];
        } else if ($required) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit();
        }
    }

    $tripData['creatorPersonID'] = $gibbonPersonID;
    $tripData['gibbonSchoolYearID'] = $gibbonSchoolYearID;
    
    //Load Trip People
    $tripPeople = [];

    $teachers = $_POST['teachers'] ?? [];
    foreach ($teachers as $person) {
        $tripPeople[] = ['role' => 'Teacher', 'gibbonPersonID' => $person];
    }

    //If no teachers have been added, throw an error.
    if (empty($tripPeople)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit();
    } 

    $students = $_POST['students'] ?? [];
    foreach ($students as $person) {          
        $tripPeople[] = ['role' => 'Student', 'gibbonPersonID' => $person];
    }

    //Load Trip Days
    $tripDays = [];

    $dateTimeOrder = $_POST['dateTimeOrder'] ?? [];
    foreach ($dateTimeOrder as $order) {
        $day = $_POST['dateTime'][$order];

        //TODO: Validation
        if (empty($day['startDate']) || empty($day['endDate'])) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit();
        }

        $day['startDate'] = Format::dateConvert($day['startDate']);
        $day['endDate'] = Format::dateConvert($day['endDate']);

        if (!empty($day['startTime']) && !empty($day['endTime'])) {
            $day['allDay'] = '0';
            //TODO: Validate start and end time
        } else {
            $day['allDay'] = '1';
            unset($day['startTime']);
            unset($day['endTime']);
        }

        $tripDays[] = $day;
    }

    //If no days have been added, throw an error.
    if (empty($tripDays)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit();
    }

    //TODO: DateTime overlap validation 

    //Load Trip Costs
    $tripCosts = [];

    $costOrder = $_POST['costOrder'] ?? [];
    foreach ($costOrder as $order) {
        $cost = $_POST['cost'][$order];

        if (empty($cost['title']) || empty($cost['cost']) || $cost['cost'] < 0) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit();
        }

        $tripCosts[] = $cost;
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
        exit();
    } 

    //TODO: Check if success, rollback if not

    //Insert new Trip People data and remove old data (if exists).
    $tripPersonGateway = $container->get(TripPersonGateway::class);
    $tripPersonGateway->deleteWhere(['tripPlannerRequestID' => $tripPlannerRequestID]);
    $tripPersonGateway->bulkInsert($tripPlannerRequestID, $tripPeople);

    //Insert new Trip Cost data and remove old data (if exists).
    $tripCostGateway = $container->get(TripCostGateway::class);
    $tripCostGateway->deleteWhere(['tripPlannerRequestID' => $tripPlannerRequestID]);
    $tripCostGateway->bulkInsert($tripPlannerRequestID, $tripCosts);

    //Insert new Trip Day data and remove old data (if exists).
    $tripDayGateway = $container->get(TripDayGateway::class);
    $tripDayGateway->deleteWhere(['tripPlannerRequestID' => $tripPlannerRequestID]);
    $tripDayGateway->bulkInsert($tripPlannerRequestID, $tripDays);

    $groupGateway = $container->get(GroupGateway::class);
    $createGroup = (isset($_POST['createGroup'])) ? $_POST['createGroup'] : 'N' ;

    //Clear Group Data
    if ($edit) {
        if ($groupGateway->exists($trip['messengerGroupID'])) {
            $groupID = $trip['messengerGroupID'];
            $groupGateway->deletePeopleByGroupID($groupID);
        }
    } else if ($createGroup == 'Y') {
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

    $tripLogGateway = $container->get(TripLogGateway::class);
    $tripLogGateway->insert([
        'tripPlannerRequestID' => $tripPlannerRequestID,
        'gibbonPersonID' => $gibbonPersonID,
        'action' => $edit ? 'Edit' : 'Request'
    ]);

    $tripGateway->commit();

    if (!$edit) {
        $notificationGateway = $container->get(notificationGateway::class);
        $notificationSender = new NotificationSender($notificationGateway, $gibbon->session);

        $event = new NotificationEvent('Trip Planner', 'New Trip Request');

        $event->setNotificationText(sprintf(__('A new Trip has been Requested (%1$s).'), $tripData['title']));
        $event->setActionLink('/index.php?q=/modules/Trip Planner/trips_requestApprove.php&tripPlannerRequestID=' . $tripPlannerRequestID);

        //TODO: Add scope?

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
        $notificationSender->sendNotifications();
    }

    $URL .= '&return=success0&tripPlannerRequestID=' . $tripPlannerRequestID . ($edit ? '&mode=edit' : '');
    header("Location: {$URL}");
    exit();
}
?>
