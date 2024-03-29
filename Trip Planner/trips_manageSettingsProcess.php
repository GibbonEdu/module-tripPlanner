<?php

use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';
require_once "./moduleFunctions.php";

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/' . $session->get('module');

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manageSettings.php')) {
    //Acess denied
    $URL .= '/trips_manage.php&return=error0';
    header("Location: {$URL}");
    exit();
} else {
    $return = 'success0';

    $settingGateway = $container->get(SettingGateway::class);

    foreach (getSettings($container, $guid) as $setting) {
        $data = $_POST[$setting->getName()] ?? null;

        $data = $setting->process($data);

        if ($data === false) {
            $return = 'warning1';
            continue;
        }

        if (!$settingGateway->updateSettingByScope('Trip Planner', $setting->getName(), $data)) {
            $return = 'warning1';
        }
    }

    $URL .= '/trips_manageSettings.php&return=' . $return;
    header("Location: {$URL}");
    exit();
}   
?>
