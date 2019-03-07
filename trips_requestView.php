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

//Module includes
include "./modules/Trip Planner/moduleFunctions.php";

$page->breadcrumbs->add(__('Manage Trip Requests'), 'trips_manage.php');
$page->breadcrumbs->add(__('View Request'));

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    $page->addError(__("You do not have access to this action."));
} else {

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $highestAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);
    if ($highestAction != false) {
        if (isset($_GET["tripPlannerRequestID"])) {
            $tripPlannerRequestID = $_GET["tripPlannerRequestID"];

            $gibbonPersonID = $_SESSION[$guid]["gibbonPersonID"];
            $departments = getHOD($connection2, $gibbonPersonID);
            $departments2 = getDepartments($connection2, getOwner($connection2, $tripPlannerRequestID));
            $isHOD = false;

            foreach ($departments as $department) {
                if (in_array($department["gibbonDepartmentID"], $departments2)) {
                    $isHOD = true;
                    break;
                }
            }

            if (isApprover($connection2, $gibbonPersonID) || isOwner($connection2, $tripPlannerRequestID, $gibbonPersonID) || isInvolved($connection2, $tripPlannerRequestID, $gibbonPersonID) || $isHOD || $highestAction == "Manage Trips_full") {
                renderTrip($guid, $connection2, $tripPlannerRequestID, false);
            } else {
                $page->addError(__('You do not have access to this action.'));
            }
        } else {    
            print "<div class='error'>";
            $page->addError(__('No request selected.'));
        }
    }
}   
?>
