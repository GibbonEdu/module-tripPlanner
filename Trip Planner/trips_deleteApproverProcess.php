<?php
use Gibbon\Module\TripPlanner\Domain\ApproverGateway;

require_once '../../gibbon.php';

$URL = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/' . $gibbon->session->get('module') . '/trips_manageApprovers.php';

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_deleteApproverProcess.php')) {
    //Acess denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit();
} else {
    $tripPlannerApproverID = $_POST['tripPlannerApproverID'] ?? '';

    $approverGateway = $container->get(ApproverGateway::class);

    if (empty($tripPlannerApproverID) || !$approverGateway->exists($tripPlannerApproverID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit(); 
    } else {
        //TODO: Fix sequence numbers?
        if ($approverGateway->delete($tripPlannerApproverID)) {
            $URL .= '&return=success0';
        } else {
            $URL .= '&return=error2';
        }
        header("Location: {$URL}");
        exit();
    }
}   
?>
