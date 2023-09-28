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

use Gibbon\View\View;
use Gibbon\Services\Format;
use Gibbon\Tables\View\GridView;
use Gibbon\Tables\Prefab\ReportTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Module\TripPlanner\Domain\TripPersonGateway;

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
        $gibbonPersonID = $session->get('gibbonPersonID');
        $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

        if (hasAccess($container, $tripPlannerRequestID, $gibbonPersonID, $highestAction)) {
            $viewMode = $_REQUEST['format'] ?? '';

            $tripPersonGateway = $container->get(TripPersonGateway::class);
            $personCriteria = $tripPersonGateway->newQueryCriteria()
                ->filterBy('tripPlannerRequestID', $tripPlannerRequestID)
                ->filterBy('role', 'Student');

            $students = $tripPersonGateway->queryTripPeople($personCriteria)->getColumn('gibbonPersonID');

            $settingGateway = $container->get(SettingGateway::class);
            $contactPhone = $settingGateway->getSettingByScope('Trip Planner', 'contactPhone');
            $contactAddress = $settingGateway->getSettingByScope('Trip Planner', 'contactAddress');

            //Prep lead teacher
            $lead = '';
            $userGateway = $container->get(UserGateway::class);
            $leadResult = $userGateway->getByID($trip['creatorPersonID']);
            if (!empty($leadResult)) {
                $lead =  Format::name($leadResult['title'], $leadResult['preferredName'], $leadResult['surname'], 'Staff', false, true);
                $leadPhone = Format::phone($leadResult['phone1'], $leadResult['phone1CountryCode']);
            }

            $cards = [];
            for ($n = 0; $n < count($students); $n++) {
                $cards[] = [
                    'organisationName' => $session->get('organisationName'),
                    'contactPhone'     => $contactPhone,
                    'contactAddress'   => $contactAddress,
                    'event'            => $trip['title'],
                    'location'         => $trip['location'],
                    'lead'             => $lead,
                    'leadPhone'        => $leadPhone,
                ];
            }

            $table = ReportTable::createPaginated('cards', $personCriteria)->setViewMode($viewMode, $session);

            $table->setRenderer(new GridView($container->get('twig')));

            $table->addMetaData('gridClass', 'items-stretch -mx-2');
            $table->addMetaData('gridItemClass', 'w-1/2 px-2 mb-4 text-center text-xs items-stretch');

            $templateView = $container->get(View::class);
            $table->addColumn('card', '')
                ->addClass('border border-gray-900 h-full')
                ->format(function ($card) use (&$templateView) {
                    return $templateView->fetchFromTemplate('printableCard.twig.html', $card);
                });

            echo $table->render($cards ?? []);

        } else {
            echo Format::alert(__('You do not have access to this action.'));
        }
    } else {
        echo Format::alert(__('No request selected'));
    }
}
