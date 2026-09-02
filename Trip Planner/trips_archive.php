<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

$page->breadcrumbs->add(__('Trip Archive'));

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_archive.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $gibbonPersonID = $session->get('gibbonPersonID');

    //Settings
    $settingGateway = $container->get(SettingGateway::class);
    
    $expiredUnapproved = $settingGateway->getSettingByScope('Trip Planner', 'expiredUnapprovedFilter');

    // Select school year
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

    $search = $_POST['search'] ?? '';

    // SEARCH
    $form = Form::create('tripFilters', $session->get('absoluteURL') . '/index.php?q=' . $_GET['q'] . '&gibbonSchoolYearID=' . $gibbonSchoolYearID);
    $form->setTitle(__('Search'));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

    $row = $form->addRow();
        $row->addLabel('search', __('Search For'))->description(__('Title, owner'));
        $row->addTextField('search')->setValue($search);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Search'), ['gibbonSchoolYearID']);

    echo $form->getOutput();

    //Trips Data
    $tripGateway = $container->get(TripGateway::class);
    $criteria = $tripGateway->newQueryCriteria(true)
        ->searchBy($tripGateway->getSearchableColumns(), $search)
        ->sortBy('firstDayOfTrip', 'DESC')
        ->filterBy('showActive', 'Y')
        ->filterBy('status', 'Approved')
        ->fromPOST();

    $trips = $tripGateway->queryTrips($criteria, $gibbonSchoolYearID, null, null, $expiredUnapproved);

    //Trips Table
    $table = DataTable::createPaginated('trips', $criteria);
    $table->setTitle(__('Past Trips'));

    $table->modifyRows(function (&$trip, $row) {
        if ($trip['status'] == 'Approved') $row->addClass('success');
        if ($trip['status'] == 'Draft') $row->addClass('dull');
        if ($trip['status'] == 'Awaiting Final Approval') $row->addClass('message');
        if ($trip['status'] == 'Rejected' || $trip['status'] == 'Cancelled') $row->addClass('dull');
        if ($trip['status'] == 'Pre-Approved') $row->addClass('bg-blue-200');

        return $row;
    });
    
    $table->addMetaData('post', ['gibbonSchoolYearID' => $gibbonSchoolYearID]);
    
    $table->addExpandableColumn('contents')
        ->format(function ($trip) {
            return formatExpandableSection(__('Description'), $trip['description']);
        });

    $table->addColumn('tripTitle', __('Title'))
        ->format(function ($trip) {
            return $trip['tripTitle'];
        });

    $table->addColumn('owner', __('Owner'))
        ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]))
        ->sortable('surname');

    $table->addColumn('firstDayOfTrip', __('First Day of Trip'))
        ->format(Format::using('dateReadable', ['firstDayOfTrip']));
                
    $table->addActionColumn()
        ->addParam('tripPlannerRequestID')
        ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
        ->format(function ($trip, $actions) {
            if ($trip['status'] != 'Approved') return;

            $actions->addAction('view', __('View Details'))
             ->setURL('/modules/Trip Planner/trips_archiveView.php');
    });
        
    echo $table->render($trips);
}
