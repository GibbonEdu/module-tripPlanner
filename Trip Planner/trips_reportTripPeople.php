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

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Tables\View\GridView;

require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    //Acess denied
    echo Format::alert(__('You do not have access to this action.'));
} else {    
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

                //TODO: Migrate to Gateway
                try {
                    $data = array('tripPlannerRequestID' => $tripPlannerRequestID, 'role' => 'Student');
                    $sql = 'SELECT gibbonPerson.title, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.image_240
                            FROM gibbonPerson
                            INNER JOIN tripPlannerRequestPerson ON (tripPlannerRequestPerson.gibbonPersonID=gibbonPerson.gibbonPersonID)
                            WHERE tripPlannerRequestPerson.tripPlannerRequestID=:tripPlannerRequestID
                            AND tripPlannerRequestPerson.role=:role';
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch(PDOException $e) {
                }

                //TODO: Migrate to ReportTable
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

                echo $table->render($result->toDataSet());
            } else {
                echo Format::alert(__('You do not have access to this action.'));
            }
        } else {    
            echo Format::alert(__('No request selected.'));
        }
    }
}
