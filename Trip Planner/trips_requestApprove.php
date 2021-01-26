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

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs
        ->add(__('Manage Trip Requests'), 'trips_manage.php')
        ->add(__('Approve Request'));

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $gibbonPersonID = $gibbon->session->get('gibbonPersonID');

    $tripGateway = $container->get(TripGateway::class);

    $tripPlannerRequestID = $_GET['tripPlannerRequestID'] ?? '';
    $trip = $tripGateway->getByID($tripPlannerRequestID);

    if (empty($trip)) {
        $page->addError(__('Invalid Trip Request Selected.'));
    } else {
        if (needsApproval($container, $gibbonPersonID, $tripPlannerRequestID)) {
            renderTrip($guid, $connection2, $tripPlannerRequestID, true);
        } else {
            $page->addError(__('You do not have access to this action.'));
        }
    } 
}   
?>
