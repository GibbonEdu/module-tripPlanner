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

use Gibbon\Forms\Form;

$page->breadcrumbs
    ->add(__('Risk Assessment Templates'), 'trips_manageRiskTemplates.php')
    ->add(__('Add Template'));

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manageRiskTemplates.php')) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    if (isset($_GET['return'])) {
        $editLink = null;
        if(isset($_GET['tripPlannerRiskTemplateID'])) {
            $editLink = $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/' . $gibbon->session->get('module') . '/trips_editRiskTemplate.php&tripPlannerRiskTemplateID=' . $_GET['tripPlannerRiskTemplateID'];
        }
        returnProcess($guid, $_GET['return'], $editLink, null);
    }   

    $form = Form::create('addRiskTemplate', $gibbon->session->get('absoluteURL') . '/modules/' . $gibbon->session->get('module') . '/trips_addRiskTemplateProcess.php');
    $form->addHiddenValue('address', $gibbon->session->get('address'));
    $form->setTitle('Add Risk Assessment Template');

    $row = $form->addRow();
        $row->addLabel('name', 'Name');
        $row->addTextfield('name')
            ->setRequired(true)
            ->maxLength(30)
            ->uniqueField('./modules/' . $gibbon->session->get('module') . '/trips_addRiskTemplateAjax.php');

    $row = $form->addRow();
        $column = $row->addColumn();
        $column->addLabel('body', 'Body');
        $column->addEditor('body', $guid)
            ->setRequired(true);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    print $form->getOutput();
}   
?>
