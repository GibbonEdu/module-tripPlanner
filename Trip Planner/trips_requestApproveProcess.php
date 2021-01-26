<?php

use Gibbon\Comms\NotificationEvent;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\TripPlanner\Domain\ApproverGateway;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Module\TripPlanner\Domain\TripLogGateway;

require_once '../../gibbon.php';
require_once "./moduleFunctions.php";

$URL = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/' . $gibbon->session->get('module');

$gibbonPersonID = $gibbon->session->get('gibbonPersonID');

$approverGateway = $container->get(ApproverGateway::class);
$approver = $approverGateway->selectApproverByPerson($gibbonPersonID);
$isApprover = !empty($approver);
$finalApprover = $isApprover ? $approver['finalApprover'] : false;

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php') || !$isApprover) {
    //Acess denied
    $URL .= '/trips_manage.php&return=error0';
    header("Location: {$URL}");
    exit();
} else {
    $tripPlannerRequestID = $_POST['tripPlannerRequestID'] ?? '';

    $tripGateway = $container->get(TripGateway::class);
    $trip = $tripGateway->getByID($tripPlannerRequestID);

    if (!empty($trip)) {
        $settingGateway = $container->get(SettingGateway::class);
        $riskAssessmentApproval = $settingGateway->getSettingByScope('Trip Planner', 'riskAssessmentApproval');

        $title = $trip['title'];
        $status = $trip['status'];
        if (needsApproval($container, $gibbonPersonID, $tripPlannerRequestID)) {
            $URL .= '/trips_requestApprove.php&tripPlannerRequestID=' . $tripPlannerRequestID;

            $action = $_POST['action'] ?? '';
            $comment = $_POST['comment'] ?? '';

            if (empty($action) || (empty($comment) && $action == 'Comment')) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit();
            }
            
            $tripLogGateway = $container->get(TripLogGateway::class);

            if ($approval == 'Approval') {
                if($status == 'Awaiting Final Approval') {
                    $tripGateway->update($tripPlannerRequestID, ['status' => 'Approved']);


                    requestNotification($guid, $connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"], 'Approved');
                } else {
                    $done = false;
                    $requestApprovalType = $settingGateway->getSettingByScope('Trip Planner', 'requestApprovalType');
                    if ($requestApprovalType == 'One Of') {
                        $done = true;
                    } elseif ($requestApprovalType == 'Two Of') {
                        $approvalLog = $tripLogGateway->selectBy([
                            'tripPlannerRequestID' => $trip['tripPlannerRequestID'],
                            'action' => 'Approval - Partial'
                        ]);

                        $done = ($approvalLog->rowCount() == 1);
                    } elseif ($requestApprovalType == 'Chain Of All') {
                        $nextApprover = $approverGateway->selectNextApprover($tripPlannerRequestID, $gibbonPersonID);
                        $done = $nextApprover->rowCount() == 0;
                    }

                    if ($done) {
                        $approval = "Approval - Final";
                            $status = "Approved";
                            if($riskAssessmentApproval) {
                                $status = "Awaiting Final Approval";
                                $approval = "Approval - Awaiting Final Approval";
                            }

                        if (!$tripGateway->update($tripPlannerRequestID, ['status' => $status])) {
                            $URL .= 'trips_manage.php&return=error2';
                            header("Location: {$URL}");
                            exit();
                        }

                        requestNotification($guid, $connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"], $status);

                        if ($status == "Approved") {
                            //Custom notifications for final approval
                            $event = new NotificationEvent('Trip Planner', 'Trip Request Approval');

                            $notificationText = sprintf(__m('Trip %1$s has been approved.'), $title);

                            $event->setNotificationText($notificationText);
                            $event->setActionLink('/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID='.$tripPlannerRequestID);

                            $event->sendNotifications($pdo, $gibbon->session);
                        }

                    } elseif ($nextApprover->isNotEmpty()) {
                        $nextApprover = $nextApprover->fetch();

                        $message = __('A trip request is awaiting your approval.');
                        setNotification($connection2, $guid, $nextApprover["gibbonPersonID"], $message, 'Trip Planner', '/index.php?q=/modules/Trip Planner/trips_requestApprove.php&tripPlannerRequestID=$tripPlannerRequestID');
                    }

                    $tripPlannerRequestLogID = $tripLogGateway->insert([
                        'tripPlannerRequestID' => $tripPlannerRequestID,
                        'gibbonPersonID' => $gibbonPersonID,
                        'action' => $approval,
                        'comment' => $comment;
                    ]);

                    if (!$tripPlannerRequestLogID) {
                        $URL .= "trips_manage.php&return=error2";
                        header("Location: {$URL}");
                        exit();
                    }
                }
            } elseif ($action == 'Rejection') {
                $tripGateway->update($tripPlannerRequestID, ['status' => 'Rejected']);

                requestNotification($guid, $connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"], "Rejected");
            } elseif ($approval == "Comment") {
                $tripPlannerRequestLogID = $tripLogGateway->insert([
                    'tripPlannerRequestID' => $tripPlannerRequestID,
                    'gibbonPersonID' => $gibbonPersonID,
                    'action' => 'Comment',
                    'comment' => $comment
                ]);

                if (!$tripPlannerRequestLogID) {
                    $URL .= 'trips_manage.php&return=error2';
                    header("Location: {$URL}");
                    exit();
                }

                requestNotification($guid, $connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"], "Comment");
            }

            $URL =  . "/index.php?q=/modules/Trip Planner/trips_manage.php&return=success0";
            header("Location: {$URL}");
            exit();
        }
    } else {
        $URL .= '/trips_manage.php&return=error1';
        header("Location: {$URL}");
        exit();
    }
}
?>
