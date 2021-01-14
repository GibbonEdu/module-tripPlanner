<?php

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\TripPlanner\Domain\RiskTemplateGateway;

require_once '../../gibbon.php';
require_once "./moduleFunctions.php";

$URL = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/' . $gibbon->session->get('module');

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manageSettings.php')) {
    //Acess denied
    $URL .= '/trips_manage.php&return=error0';
    header("Location: {$URL}");
    exit();
} else {
    $return = 'success0';

    $settingGateway = $container->get(SettingGateway::class);
    $riskTemplateGateway = $container->get(RiskTemplateGateway::class);

    foreach (getSettings($guid, $riskTemplateGateway) as $key => $value) {
        $data = $_POST[$key] ?? null;

        $data = $value['process']($data);

        if ($data === false) {
            $return = 'warning1';
            continue;
        }

        if (!$settingGateway->updateSettingByScope('Trip Planner', $key, $data)) {
            $return = 'warning1';
        }

    }

    $URL .= '/trips_manageSettings.php&return=' . $return;
    header("Location: {$URL}");
    exit();
}   
?>
