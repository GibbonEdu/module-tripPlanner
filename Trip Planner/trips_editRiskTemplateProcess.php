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

    $riskTemplateGateway = $container->get(RiskTemplateGateway::class);

    $tripPlannerRiskTemplateID = $_POST['tripPlannerRiskTemplateID'] ?? '';    

    if (empty($tripPlannerRiskTemplateID) || !$riskTemplateGateway->exists($tripPlannerRiskTemplateID)) {
        $URL .= '/trips_manageRiskTemplates.php&return=error1';
        header("Location: {$URL}");
        exit();
    } else {
        $URL .= '/trips_editRiskTemplate.php';
        $data = [
            'name' => $_POST['name'] ?? '',
            'body' => $_POST['body'] ?? '',
        ];
        if (empty($data['name']) || empty($data['body']) || !$riskTemplateGateway->unique($data, ['name'], $tripPlannerRiskTemplateID)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit();
        } else {
            if (!$riskTemplateGateway->update($tripPlannerRiskTemplateID, $data)) {
                $URL .= '&return=error2';
            } else {
                $URL .= '&return=success0&tripPlannerRiskTemplateID=' . $tripPlannerRiskTemplateID;
            }

            header("Location: {$URL}");
            exit();
        }
    }
}   
?>
