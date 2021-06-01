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

use Gibbon\Forms\Prefab\DeleteForm;
use Gibbon\Module\TripPlanner\Domain\ApproverGateway;

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_deleteApproverProcess.php')) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $approverGateway = $container->get(ApproverGateway::class);

    $tripPlannerApproverID = $_GET['tripPlannerApproverID'] ?? '';

    if (empty($tripPlannerApproverID) || !$approverGateway->exists($tripPlannerApproverID)) {
        $page->addError(__('Invalid Approver.'));
    } else {
        $form = DeleteForm::createForm($gibbon->session->get('absoluteURL') . '/modules/' . $gibbon->session->get('module') . "/trips_deleteApproverProcess.php");
        $form->addHiddenValue('address', $gibbon->session->get('address'));
        $form->addHiddenValue('tripPlannerApproverID', $tripPlannerApproverID);

        echo $form->getOutput();
    }
}
?>
