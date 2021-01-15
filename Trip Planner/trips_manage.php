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

use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\Form;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Manage Trip Requests'));

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $highestAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);
    if ($highestAction != false) { 
        $settingGateway = $container->get(SettingGateway::class);
        
        $expenseApprovalType = $settingGateway->getSettingByScope('Trip Planner', 'requestApprovalType');
        $riskAssessmentApproval = $settingGateway->getSettingByScope('Trip Planner', 'riskAssessmentApproval');
        $eutFilter = $settingGateway->getSettingByScope('Trip Planner', 'expiredUnapprovedFilter');

        $gibbonPersonID = $gibbon->session->get('gibbonPersonID');

        $ama = (isApprover($connection2, $gibbonPersonID) && $expenseApprovalType == 'Chain Of All') || ($riskAssessmentApproval && isApprover($connection2, $gibbonPersonID, true));
        $departments = getHOD($connection2, $gibbonPersonID);
        $isHOD = $departments->rowCount() > 0;

        $relations = array();
        $relationFilter = 'MR';

        if ($highestAction == 'Manage Trips_full') {
            $relations[''] = 'All Requests';
            $relationFilter = '';
        }

        $relations['MR'] = 'My Requests';
        $relations['I'] = 'Involved';

        if ($isHOD) {
            while ($department = $departments->fetch()) {
                $relations['DR' . $department['gibbonDepartmentID']] = 'Department Requests - ' . $department['nameShort'];
            }
        }

        if ($ama) {
            $relations['AMA'] = 'Awaiting My Approval';
            $relationFilter = 'AMA';
        }

        $statusFilter = $_POST['statusFilter'] ?? 'Requested';
        $yearFilter = $_POST['yearFilter'] ?? $gibbon->session->get('gibbonSchoolYearID');

        if (isset($_POST['relationFilter'])) {
            $relationFilter = $_POST['relationFilter'];
        }

        $schoolYearGateway = $container->get(SchoolYearGateway::class);
        foreach ($schoolYearGateway->querySchoolYears($schoolYearGateway->newQueryCriteria())->toArray() as $year) {
            $years[$year['gibbonSchoolYearID']] = $year['name'];
        }

        //Filter Form
        $form = Form::create('tripFilters', $_SESSION[$guid]['absoluteURL'] . '/index.php?q=' . $_GET['q']);

        $form->setTitle(__('Filter'));

        $row = $form->addRow();
            $row->addLabel('statusFilter', 'Status Filter');
            $row->addSelect('statusFilter')
                ->fromArray(array('All', 'Requested', 'Approved', 'Rejected', 'Cancelled', 'Awaiting Final Approval'))
                ->selected($statusFilter);

        $row = $form->addRow();
            $row->addLabel('relationFilter', 'Relation Filter');
            $row->addSelect('relationFilter')
                ->fromArray($relations)
                ->selected($relationFilter);

        $row = $form->addRow();
            $row->addLabel('yearFilter', 'Year Filter');
            $row->addSelect('yearFilter')
                ->fromArray($years)
                ->selected($yearFilter);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        print $form->getOutput();
      
        //Trips Data
        $tripGateway = $container->get(TripGateway::class);
        $criteria = $tripGateway->newQueryCriteria(true)
            ->filterBy('status', serialize($statusFilter))
            ->filterBy('relation', $relationFilter . ':' . $gibbonPersonID)
            ->filterBy('year')
            ->filterBy('eutfilter', $eutFilter)
            ->fromPOST();

        $trips = $tripGateway->queryTrips($criteria);

        //Trips Table
        $table = DataTable::createPaginated('trips', $criteria);
        $table->setTitle(__('Requests'));
      
        $table->addHeaderAction('add', __('Submit Request'))
          ->displayLabel()
          ->setURL('/modules/Trip Planner/trips_submitRequest.php');
      
        $table->addColumn('tripTitle', __('Title'));
      
        $table->addColumn('description', __('Description'));
      
        $table->addColumn('owner', __('Owner'))
          ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]));

        $table->addColumn('status', __('Status'));
                   
        $table->addActionColumn()
        ->addParam('tripPlannerRequestID')
        ->format(function ($row, $actions) use ($guid, $connection2) {
            $actions->addAction('approve/reject', __('Approve/Reject'))
                ->setURL('/modules/Trip Planner/trips_requestApprove.php')
                ->setIcon('iconTick');
        });
                   
        $table->addActionColumn()
          ->addParam('tripPlannerRequestID')
          ->format(function ($row, $actions) use ($connection2, $gibbon) {
              $actions->addAction('view', __('View Details'))
                ->setURL('/modules/Trip Planner/trips_requestView.php');

            if ($gibbon->session->get('gibbonPersonID') == $row['creatorPersonID'] &&
                  $row['status'] != 'Cancelled' &&
                  $row['status'] != 'Rejected'
              ) {
                $actions->addAction('edit', __('Edit'))
                ->addParam('mode', 'edit')
                ->setURL('/modules/Trip Planner/trips_submitRequest.php');
            }
            
            if (($row['status'] == 'Requested' && needsApproval($connection2, $row['tripPlannerRequestID'], $gibbon->session->get('gibbonPersonID')) == 0)
                || ($row['status'] == 'Awaiting Final Approval' && isApprover($connection2, $gibbon->session->get('gibbonPersonID'), true))) {
                $actions->addAction('approve', __('Approve/Reject'))
                    ->setURL('/modules/Trip Planner/trips_requestApprove.php')
                    ->setIcon('iconTick');
            }
          });
          echo $table->render($trips);
    } else {
        $page->addError(__('Highest grouped action could not be determined.'));
    }
}
