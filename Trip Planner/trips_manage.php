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

use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\Form;
use Gibbon\Module\TripPlanner\Domain\ApproverGateway;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Forms\DatabaseFormFactory;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Manage Trip Requests'));

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $highestAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);

    if (empty($highestAction)) {
        $page->addError(__('The highest grouped action cannot be determined.'));
        return;   
    }
    $gibbonPersonID = $session->get('gibbonPersonID');

    //Settings
    $settingGateway = $container->get(SettingGateway::class);
    
    $requestApprovalType = $settingGateway->getSettingByScope('Trip Planner', 'requestApprovalType');
    $riskAssessmentApproval = $settingGateway->getSettingByScope('Trip Planner', 'riskAssessmentApproval');
    $expiredUnapproved = $settingGateway->getSettingByScope('Trip Planner', 'expiredUnapprovedFilter');

    //Permissions
    $approverGateway = $container->get(ApproverGateway::class);
    
    $approver = $approverGateway->selectApproverByPerson($gibbonPersonID);
    $isApprover = !empty($approver);
    $finalApprover = $isApprover ? boolval($approver['finalApprover']) : false;

    $checkAwaitingApproval = ($isApprover && $requestApprovalType == 'Chain Of All')
        || ($riskAssessmentApproval && $finalApprover);

    //Department Data
    $departmentGateway = $container->get(DepartmentGateway::class);
    $departmentsList = $departmentGateway->selectDepartmentsByPerson($gibbonPersonID, 'Coordinator');
    
    $departments = array_reduce($departmentsList->fetchAll(), function ($group, $department) {
        $group[$department['gibbonDepartmentID']] = $department['name'];
        return $group;
    }, []);

    //Filters
    $gibbonDepartmentID = $_POST['gibbonDepartmentID'] ?? []; 
    $gibbonSchoolYearID = $_POST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    //Filter Form
    $form = Form::create('tripFilters', $gibbon->session->get('absoluteURL') . '/index.php?q=' . $_GET['q']);
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setTitle(__('Filter'));

    if (!empty($departments)) {
        $row = $form->addRow();
            $row->addLabel('gibbonDepartmentID', 'Department');
            $row->addSelect('gibbonDepartmentID')
                ->fromArray($departments)
                ->placeholder()
                ->selected($gibbonDepartmentID);
    }

    $row = $form->addRow();
        $row->addLabel('gibbonSchoolYearID', 'Year');
        $row->addSelectSchoolYear('gibbonSchoolYearID')
            ->selected($gibbonSchoolYearID);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    print $form->getOutput(); 

    //Trips Data
    $tripGateway = $container->get(TripGateway::class);
    $criteria = $tripGateway->newQueryCriteria(true)
        ->sortBy('firstDayOfTrip', 'DESC')
        ->filterBy('showActive', 'Y')
        ->fromPOST();

    $gibbonPersonIDFilter = $highestAction == 'Manage Trips_full' || $highestAction == 'Manage Trips_view'
        ? null
        : $gibbonPersonID;

    $trips = $tripGateway->queryTrips($criteria, $gibbonSchoolYearID, $gibbonPersonIDFilter, $gibbonDepartmentID, $expiredUnapproved);
    $trips->transform(function (&$trip) use ($container, $gibbonPersonID, $checkAwaitingApproval) {
        $trip['canApprove'] = 'N';

        //TODO: Migrate to gateway/SQL
        if ($checkAwaitingApproval) {
            if (needsApproval($container, $gibbonPersonID, $trip['tripPlannerRequestID'])) {
                $trip['canApprove'] = 'Y';
            }
        }
    });

    //Trips Table
    $table = DataTable::createPaginated('trips', $criteria);
    $table->setTitle(__('Requests'));

    $table->modifyRows(function (&$trip, $row) {
        if ($trip['status'] == 'Approved') $row->addClass('success');
        if ($trip['status'] == 'Draft') $row->addClass('dull');
        if ($trip['status'] == 'Awaiting Final Approval') $row->addClass('message');
        if ($trip['status'] == 'Rejected' || $trip['status'] == 'Cancelled') $row->addClass('dull');

        return $row;
    });

    $filters = array_reduce(getStatuses(), function($filters, $status) {
        $filters['status:' . $status] = __('Status') . ': ' . __($status);
        return $filters;
    });

    $filters['showActive:Y'] = __m('Upcoming / Approved Trips');
    
    $table->addMetaData('filterOptions', $filters);
    
    $table->addHeaderAction('add', __('Submit Request'))
        ->displayLabel()
        ->setURL('/modules/Trip Planner/trips_submitRequest.php');
    
    $table->addExpandableColumn('contents')
        ->format(function ($trip) {
            return formatExpandableSection(__('Description'), $trip['description']);
        });

    $table->addColumn('tripTitle', __('Title'))
        ->format(function ($trip) {
            return $trip['tripTitle'].($trip['status'] == 'Draft' ? Format::tag(__('Draft'), 'message ml-2') : '');
        });

    $table->addColumn('owner', __('Owner'))
        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]))
        ->sortable('surname');

    $table->addColumn('firstDayOfTrip', __('First Day of Trip'))
        ->format(Format::using('dateReadable', ['firstDayOfTrip']));

    $table->addColumn('status', __('Status'))->format(function($trip) {
        $output = $trip['status'];
        $output .= $trip['canApprove'] == 'Y' && $trip['status'] == 'Requested' 
            ? Format::tag(__m('Awaiting Approval'), 'message ml-2') 
            : '';

        return $output;
    });
                
    $table->addActionColumn()
        ->addParam('tripPlannerRequestID')
        ->format(function ($trip, $actions) use ($container, $gibbonPersonID, $highestAction)  {
            $actions->addAction('view', __('View Details'))
            ->setURL('/modules/Trip Planner/trips_requestView.php');

        if (($highestAction == 'Manage Trips_full' || $gibbonPersonID == $trip['creatorPersonID']) && !in_array($trip['status'], ['Cancelled', 'Rejected'])) {
            $actions->addAction('edit', __('Edit'))
                ->addParam('mode', 'edit')
                ->setURL('/modules/Trip Planner/trips_submitRequest.php');
        }
        
        if (needsApproval($container, $gibbonPersonID, $trip['tripPlannerRequestID'])) {
            $actions->addAction('approve', __('Approve/Reject'))
                ->setURL('/modules/Trip Planner/trips_requestApprove.php')
                ->setIcon('iconTick');
        }
    });
        
    echo $table->render($trips);
}
