<?php

use Gibbon\Module\TripPlanner\Domain\RiskTemplateGateway;

require_once '../../gibbon.php';

$URL = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/' . $gibbon->session->get('module');

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manageRiskTemplates.php')) {
    //Acess denied
    $URL .= '/trips_manage.php&return=error0';
    header("Location: {$URL}");
    exit();
} else {
    $URL .= '/trips_manageRiskTemplates.php';

    $riskTemplateGateway = $container->get(RiskTemplateGateway::class);

    $tripPlannerRiskTemplateID = $_POST['tripPlannerRiskTemplateID'] ?? '';

    if (empty($tripPlannerRiskTemplateID) || !$riskTemplateGateway->exists($tripPlannerRiskTemplateID)) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit();
    } else {
        if ($riskTemplateGateway->delete($tripPlannerRiskTemplateID)) {
            $URL .= '&return=success0';
        } else {
            $URL .= '&return=error2';
        }

        header("Location: {$URL}");
        exit();
    }
}   
?>
