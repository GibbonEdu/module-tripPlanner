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
use Gibbon\Domain\Students\MedicalGateway;
use Gibbon\Domain\Students\StudentReportGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Forms\Form;
use Gibbon\Module\TripPlanner\Domain\ApproverGateway;
use Gibbon\Module\TripPlanner\Domain\TripCostGateway;
use Gibbon\Module\TripPlanner\Domain\TripDayGateway;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Module\TripPlanner\Domain\TripPersonGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\Prefab\ReportTable;
use Gibbon\View\View;

require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    //Acess denied
    echo Format::alert(__('You do not have access to this action.'));
} else {
    $tripPlannerRequestID = $_GET['tripPlannerRequestID'] ?? '';

    $tripGateway = $container->get(TripGateway::class);
    $trip = $tripGateway->getByID($tripPlannerRequestID);

    if (!empty($trip)) {
        $highestAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);
        $gibbonPersonID = $gibbon->session->get('gibbonPersonID');
        $gibbonSchoolYearID = $gibbon->session->get('gibbonSchoolYearID');

        if (hasAccess($container, $tripPlannerRequestID, $gibbonPersonID, $highestAction)) {
            $viewMode = $_REQUEST['format'] ?? '';

            $tripPersonGateway = $container->get(TripPersonGateway::class);
            $personCriteria = $tripPersonGateway->newQueryCriteria()
                ->filterBy('tripPlannerRequestID', $tripPlannerRequestID)
                ->filterBy('role', 'Student');

            $students = $tripPersonGateway->queryTripPeople($personCriteria)->getColumn('gibbonPersonID');

            $settingGateway = $container->get(SettingGateway::class);

            $cutoffDate = $settingGateway->getSettingByScope('Data Updater', 'cutoffDate');
            if (empty($cutoffDate)) $cutoffDate = Format::dateFromTimestamp(time() - (604800 * 26));

            //EVENT DATA
            //Prep dates
            $tripDayGateway = $container->get(TripDayGateway::class);
            $dayCriteria = $tripDayGateway->newQueryCriteria()
                ->filterBy('tripPlannerRequestID', $tripPlannerRequestID)
                ->sortBy(['startDate', 'startTime', 'allDay']);

            $dates = array_reduce($tripDayGateway->queryTripDay($dayCriteria)->toArray(), function ($group, $day) {
                $group .= Format::dateRange($day['startDate'], $day['endDate']);
                $group .= ' (';

                if (boolval($day['allDay']) || $day['startTime'] == null || $day['endTime'] == null) {
                    $group .= __('All Day');
                } else {
                    $group .= Format::timeRange($day['startTime'], $day['endTime']);
                }

                $group .= ')<br />';
                return $group;
            });

            //Prep lead teacher
            $lead = '';
            $userGateway = $container->get(UserGateway::class);
            $leadResult = $userGateway->getByID($trip['creatorPersonID']);
            if (!empty($leadResult)) {
                $lead =  Format::name($leadResult['title'], $leadResult['preferredName'], $leadResult['surname'], 'Staff', false, true);
                if (!empty($leadResult['phone1'])) {
                    $lead .= ' (' . $leadResult['phone1'] . ')';
                }
            }

            echo '<h2>' . __('Trip Overview') . '</h2>';
            echo $page->fetchFromTemplate('event.twig.html', [
                'event' => $trip['title'],
                'dates' => $dates,
                'location' => $trip['location'],
                'lead' => $lead,
            ]);

            //Gateways
            $reportGateway = $container->get(StudentReportGateway::class);
            $familyGateway = $container->get(FamilyGateway::class);
            $medicalGateway = $container->get(MedicalGateway::class);

            //Emergency query
            $criteria = $reportGateway->newQueryCriteria(true)
                ->sortBy(['gibbonPerson.surname', 'gibbonPerson.preferredName'])
                ->pageSize(!empty($viewMode) ? 0 : 50)
                ->fromPOST();
            $students = $reportGateway->queryStudentDetails($criteria, $students);

            //Medial criteria
            $criteria = $reportGateway->newQueryCriteria(true)
                ->sortBy(['gibbonPerson.surname', 'gibbonPerson.preferredName'])
                ->pageSize(!empty($viewMode) ? 0 : 50)
                ->fromPOST();

           // Join a set of medical records per student
           $people = $students->getColumn('gibbonPersonID');
           $medical = $medicalGateway->queryMedicalFormsBySchoolYear($criteria, $gibbonSchoolYearID)->toArray(); //->fetchGrouped()
           $students->joinColumn('gibbonPersonID', 'medical', $medical);

           // Join a set of medical conditions per student
           $medicalIDs = $students->getColumn('gibbonPersonMedicalID');
           $medicalConditions = $medicalGateway->selectMedicalConditionsByID($medicalIDs)->fetchGrouped();
           $students->joinColumn('gibbonPersonMedicalID', 'medicalConditions', $medicalConditions);

           // Join a set of family adults per student
           $people = $students->getColumn('gibbonPersonID');
           $familyAdults = $familyGateway->selectFamilyAdultsByStudent($people, true)->fetchGrouped();
           $students->joinColumn('gibbonPersonID', 'familyAdults', $familyAdults);

            // DATA TABLE
            $table = ReportTable::createPaginated('studentEmergencySummary', $criteria)->setViewMode($viewMode, $gibbon->session);
            $table->setTitle(__('Participants'));

            $table->addMetaData('post', ['gibbonPersonID' => $students]);

            $table->addColumn('student', __('Student'))
                ->width('12%')
                ->description(__('Last Personal Update'))
                ->sortable(['gibbonPerson.surname', 'gibbonPerson.preferredName'])
                ->format(function ($student) use ($cutoffDate) {
                    $output = Format::name('', $student['preferredName'], $student['surname'], 'Student', true, true).'<br/><br/>';

                    $output .= ($student['lastPersonalUpdate'] < $cutoffDate) ? '<span style="color: #ff0000; font-weight: bold"><i>' : '<span><i>';
                    $output .= !empty($student['lastPersonalUpdate']) ? Format::date($student['lastPersonalUpdate']) : __('N/A');
                    $output .= '</i></span>';

                    return $output;
                });

            $view = new View($container->get('twig'));
            $table->addColumn('contacts', __('Parents'))
                ->width('15%')
                ->notSortable()
                ->format(function ($student) use ($view) {
                    return $view->fetchFromTemplate(
                        'formats/familyContacts.twig.html',
                        ['familyAdults' => $student['familyAdults']]
                    );
                });

            $table->addColumn('emergency1', __('Emergency Contact 1'))
                ->width('15%')
                ->sortable('emergency1Name')
                ->format(function ($student) use ($view) {
                    return $view->fetchFromTemplate(
                        'formats/emergencyContact.twig.html',
                        [
                            'name'         => $student['emergency1Name'],
                            'number1'      => $student['emergency1Number1'],
                            'number2'      => $student['emergency1Number2'],
                            'relationship' => $student['emergency1Relationship'],
                        ]
                    );
                });

            $table->addColumn('emergency2', __('Emergency Contact 2'))
                ->width('15%')
                ->sortable('emergency2Name')
                ->format(function ($student) use ($view) {
                    return $view->fetchFromTemplate(
                        'formats/emergencyContact.twig.html',
                        [
                            'name'         => $student['emergency2Name'],
                            'number1'      => $student['emergency2Number1'],
                            'number2'      => $student['emergency2Number2'],
                            'relationship' => $student['emergency2Relationship'],
                        ]
                    );
                });

            $view = new View($container->get('twig'));

            $table->addColumn('medicalForm', __('Medical Form?'))
                ->width('16%')
                ->sortable('gibbonPersonMedicalID')
                ->format(function ($student) use ($view) {
                    return $view->fetchFromTemplate('formats/medicalForm.twig.html', $student);
                });

            $table->addColumn('conditions', __('Medical Conditions'))
                ->width('60%')
                ->notSortable()
                ->format(function ($student) use ($view) {
                    return $view->fetchFromTemplate('formats/medicalConditions.twig.html', $student);
                });

            echo $table->render($students);

        } else {
            echo Format::alert(__('You do not have access to this action.'));
        }
    } else {
        echo Format::alert(__('No request selected'));
    }
}
