<?php

use Gibbon\Module\TripPlanner\Domain\ApproverGateway;

require_once '../../gibbon.php';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module');

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_addApprover.php')) {
    //Acess denied
    $URL .= '/trips_manageApprovers.php&return=error0';
    header("Location: {$URL}");
    exit();
} else {    
    $URL .= '/trips_addApprover.php';

    $approverGateway = $container->get(ApproverGateway::class);

    $gibbonPersonID = $_POST['gibbonPersonID'] ?? '';

    if (empty($gibbonPersonID) || !$approverGateway->unique(['gibbonPersonID' => $gibbonPersonID], ['gibbonPersonID'])) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit();
    } else {
        $finalApprover = isset($_POST['finalApprover']) ? 1 : 0;
        $notifyAllComments = $_POST['notifyAllComments'] ?? 'N';
        $sequenceNumber = $approverGateway->getNextSequenceNumber();

        $tripPlannerApproverID = $approverGateway->insert([
            'gibbonPersonID'    => $gibbonPersonID,
            'sequenceNumber'    => $sequenceNumber,
            'finalApprover'     => $finalApprover,
            'notifyAllComments' => $notifyAllComments,
        ]);

        if ($tripPlannerApproverID) {
            $URL .= '&return=success0';
        } else {
            $URL .= '&return=error2';
        }

        header("Location: {$URL}");
        exit();
    }
}   
?>
