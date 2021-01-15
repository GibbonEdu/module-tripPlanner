<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\Form;
use Gibbon\Module\TripPlanner\Domain\RiskTemplateGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Manage Settings'));

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manageSettings.php')) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $settingGateway = $container->get(SettingGateway::class);
    $riskTemplateGateway = $container->get(RiskTemplateGateway::class);

    $moduleName = $gibbon->session->get('module'); 

    $form = Form::create("tripPlannerSettings", $_SESSION[$guid]["absoluteURL"] . "/modules/Trip Planner/trips_manageSettingsProcess.php");
    $form->addHiddenValue('address', $gibbon->session->get('address'));
    $form->setTitle(__('Trip Planner Settings'));

    foreach (getSettings($guid, $riskTemplateGateway) as $key => $value) {
        $setting = $settingGateway->getSettingByScope('Trip Planner', $key, true);
        
        $row = $form->addRow();
        if (!$value['row']) {
            $row = $row->addColumn();
        }

        $row->addLabel($setting['name'], __($setting['nameDisplay']))
            ->description($setting['description']);

        $value['render']($setting, $row);
    }

    $fRow = $form->addRow();
        $fRow->addFooter();
        $fRow->addSubmit();

    print $form->getOutput();
}   
?>
