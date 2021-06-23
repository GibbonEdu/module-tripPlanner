<?php

use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Module\TripPlanner\Domain\TripLogGateway;

require_once '../../gibbon.php';
require_once './moduleFunctions.php';

$moduleName = $session->get('module');

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $moduleName;

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    //Acess denied
    $URL .= '/trips_manage.php&return=error0';
    header("Location: {$URL}");
    exit();
} else {
    $tripPlannerRequestID = $_POST['tripPlannerRequestID'] ?? '';

    $tripGateway = $container->get(TripGateway::class);

    if (empty($tripPlannerRequestID) || !$tripGateway->exists($tripPlannerRequestID)) {
        $URL .= '/trips_manage.php&return=error1';
        header("Location: {$URL}");
        exit();
    }

    $gibbonPersonID = $session->get('gibbonPersonID');
    $highestAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);

    if (hasAccess($container, $tripPlannerRequestID, $gibbonPersonID, $highestAction)) {
        $URL .= '/trips_requestView.php&tripPlannerRequestID=' . $tripPlannerRequestID;

        $comment = $_POST['comment'] ?? '';

        if (empty($comment)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit();
        }

        $tripLogGateway = $container->get(TripLogGateway::class);

        $tripPlannerRequestLogID = $tripLogGateway->insert([
            'tripPlannerRequestID'  => $tripPlannerRequestID,
            'gibbonPersonID'        => $gibbonPersonID,
            'action'                => 'Comment',
            'comment'               => $comment
        ]);

        if (!$tripPlannerRequestID) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit();
        }

        $notificationGateway = $container->get(NotificationGateway::class);
        $notificationSender = new NotificationSender($notificationGateway, $session);

        tripCommentNotifications($tripPlannerRequestID, $gibbonPersonID, $tripLogGateway, $notificationSender);

        $notificationSender->sendNotifications();

        $URL .= '&return=success0';
        header("Location: {$URL}");
        exit();
    } else {
        $URL .= '/trips_manage.php&return=error0';
        header("Location: {$URL}");
        exit();
    }
}   
?>
