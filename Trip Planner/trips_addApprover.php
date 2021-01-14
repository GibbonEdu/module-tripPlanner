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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\TripPlanner\Domain\ApproverGateway;

$page->breadcrumbs
        ->add(__('Manage Approvers'), 'trips_manageApprovers.php')
        ->add(__('Add Approver'));

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_addApprover.php')) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/' . $gibbon->session->get('module') . '/trips_manageApprovers.php', null);
    }   

    $approverGateway = $container->get(ApproverGateway::class);

    $form = Form::create('addApprover', $gibbon->session->get('absoluteURL') . '/modules/' . $gibbon->session->get('module') . '/trips_addApproverProcess.php', 'post');
    $form->addHiddenValue('address', $gibbon->session->get('address'));
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setTitle('Add Approver');

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', 'Staff');
        $row->addSelectPerson('gibbonPersonID')
            ->fromArray($approverGateway->selectStaffForApprover())
            ->setRequired(true)
            ->placeholder('Please select...');

    $riskAssessmentApproval = getSettingByScope($connection2, 'Trip Planner', 'riskAssessmentApproval');
    if($riskAssessmentApproval) {
        $row = $form->addRow();
            $row->addLabel('finalApprover', 'Final Approver');
            $row->addCheckbox('finalApprover');
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    print $form->getOutput();
}   
?>
