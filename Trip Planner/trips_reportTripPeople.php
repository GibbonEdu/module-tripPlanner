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

use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Module\TripPlanner\Domain\TripPersonGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Tables\View\GridView;

require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    //Acess denied
    echo Format::alert(__('You do not have access to this action.'));
} else {    
    $tripPlannerRequestID = $_GET["tripPlannerRequestID"] ?? '';

    $tripGateway = $container->get(TripGateway::class);
    $trip = $tripGateway->getByID($tripPlannerRequestID);

    if (!empty($trip)) {
        $gibbonPersonID = $gibbon->session->get('gibbonPersonID');
        $highestAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);

        if (hasAccess($container, $tripPlannerRequestID, $gibbonPersonID, $highestAction)) {
            $tripPersonGateway = $container->get(TripPersonGateway::class);

            $criteria = $tripPersonGateway->newQueryCriteria()
                ->sortBy(['surname', 'preferredName'])
                ->filterBy('tripPlannerRequestID', $tripPlannerRequestID)
                ->filterBy('role', 'Student');

            $gridRenderer = new GridView($container->get('twig'));
            $table = $container->get(DataTable::class)->setRenderer($gridRenderer);
            $table->setTitle(__('Students in Trip'));

            $table->addMetaData('gridClass', 'rounded-sm bg-blue-100 border py-2');
            $table->addMetaData('gridItemClass', 'w-1/2 sm:w-1/4 md:w-1/5 my-2 text-center');
            
            $table->addHeaderAction('print', __('Print'))
                ->setExternalURL('javascript:window.print()')
                ->displayLabel()
                ->addClass('mr-2 underline');

            $table->addColumn('image_240')
                ->format(Format::using('userPhoto', ['image_240', 'sm', '']));
            
            $table->addColumn('name')
                ->setClass('text-xs font-bold mt-1')
                ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Student', false, false]));

            echo $table->render($tripPersonGateway->queryTripPeople($criteria));
        } else {
            echo Format::alert(__('You do not have access to this action.'));
        }
    } else {    
        echo Format::alert(__('No request selected.'));
    }
}
