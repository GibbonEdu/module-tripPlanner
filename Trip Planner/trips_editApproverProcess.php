<?php

use Gibbon\Module\TripPlanner\Domain\ApproverGateway;

require_once '../../gibbon.php';

$URL = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/' . $gibbon->session->get('module') . '/trips_manageApprovers.php';

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_editApprover.php')) {
    //Acess denied
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit();
} else {    
    $approverGateway = $container->get(ApproverGateway::class);

    $tripPlannerApproverID = $_POST['tripPlannerApproverID'] ?? '';

    if (empty($tripPlannerApproverID) || !$approverGateway->exists($tripPlannerApproverID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit();
    } else {
        $finalApprover = isset($_POST['finalApprover']) ? 1 : 0;

        if ($approverGateway->update($tripPlannerApproverID, ['finalApprover' => $finalApprover])) {
            $URL .= '&return=success0';
        } else {
            $URL .= '&return=error2';
        }

        header("Location: {$URL}");
        exit();
    }
}   
?>
