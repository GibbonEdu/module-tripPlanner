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

require_once __DIR__ . '/moduleFunctions.php';

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

$page->breadcrumbs->add(__('Today\'s Trips'));

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_reportToday.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    //TODO: Migrate to Gateway
    try {
        $data = array('date' => date('Y-m-d'));
        $sql = "SELECT
                tripPlannerRequests.tripPlannerRequestID, tripPlannerRequests.timestampCreation, tripPlannerRequests.title, tripPlannerRequests.description, tripPlannerRequests.status, gibbonPerson.title as personTitle, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.gibbonPersonID
            FROM tripPlannerRequests
                JOIN tripPlannerRequestDays ON (tripPlannerRequestDays.tripPlannerRequestID=tripPlannerRequests.tripPlannerRequestID)
                LEFT JOIN gibbonPerson ON tripPlannerRequests.creatorPersonID = gibbonPerson.gibbonPersonID
            WHERE
                tripPlannerRequestDays.startDate <= :date
                AND tripPlannerRequestDays.endDate >= :date
                AND tripPlannerRequests.status IN ('Requested','Approved','Awaiting Final Approval')
            ";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        echo $e->getMessage();
    }

    $moduleName = $gibbon->session->get('module');

    //TODO: pagination?
    $table = DataTable::create('todaysTrips');
    $table->setTitle('Today\'s Trips');

    $table->addExpandableColumn('description')
        ->format(function ($trip) {
            $output = '';

            $output .= '<h6>' . __('Description') . '</h6>';
            $output .= nl2brr($trip['description']);

            return $output;
        });

    $table->addColumn('title', __('Title'));

    $table->addColumn('owner', __('Owner'))
        ->format(Format::using('name', ['personTitle', 'preferredName', 'surname', 'Staff', false, true]));

    $table->addColumn('status', __('Status'));

    $table->addActionColumn()
        ->addParam('tripPlannerRequestID')
        ->format(function ($trip, $actions) use ($moduleName) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/' . $moduleName . '/trips_requestView.php');
        });

    echo $table->render($result->toDataSet());
}
?>
