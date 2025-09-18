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

use Gibbon\Services\Format;
use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Module\TripPlanner\Domain\ApproverGateway;

//require getcwd().'/../gibbon.php';
require __DIR__.'/../../../gibbon.php';

// Setup some of the globals
getSystemSettings($guid, $connection2);
Format::setupFromSession($container->get('session'));

// Check for CLI, so this cannot be run through browser
$settingGateway = $container->get(SettingGateway::class);
$remoteCLIKey = $settingGateway->getSettingByScope('System Admin', 'remoteCLIKey');
$remoteCLIKeyInput = $_GET['remoteCLIKey'] ?? null;
if (!(isCommandLineInterface() OR ($remoteCLIKey != '' AND $remoteCLIKey == $remoteCLIKeyInput))) {
    print __("This script cannot be run from a browser, only via CLI.");
    return;
}

// Override the ini to keep this process alive
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 1800);
set_time_limit(1800);

// Initialize the notification sender & gateway objects
$gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
$notificationSender = $container->get(NotificationSender::class);
$daysBeforeStart = 3;

// SCAN THROUGH ALL UNAPPROVED TRIPS
$unapprovedTrips = $container->get(TripGateway::class)->selectApprovalPending($gibbonSchoolYearID, $daysBeforeStart)->fetchAll();
$count = count($unapprovedTrips);

// Find all the trip approvers to get notified
$approverCriteria = $container->get(ApproverGateway::class)->newQueryCriteria()->sortBy(['sequenceNumber']);
$approvers = $container->get(ApproverGateway::class)->queryApprovers($approverCriteria)->toArray();

// Loop over each approver and add a notification to send
foreach ($approvers as $approver) {
    $tripTitles = implode(', ', array_column($unapprovedTrips, 'title'));
    $actionText = __m('There are {count} pending trip request(s) whose start date are within the next {daysBeforeStart} days. Please click below or visit the Manage Trips page to approve and manage trip requests: {trips}.', ['count' => $count, 'daysBeforeStart' => $daysBeforeStart, 'trips' => $tripTitles]);
    $actionLink = '/index.php?q=/modules/Trip Planner/trips_manage.php';
    $notificationSender->addNotification($approver['gibbonPersonID'], $actionText, 'Trip Planner', $actionLink);
}

// Send out the notifications
$sendReport = $notificationSender->sendNotifications();

// Notify admin
$actionText = __m('A Trip Planner CLI script ({name}) has run.', ['name' => 'Upcoming Trips Pending Approval']).'<br/><br/>';
$actionText .= __('Date').': '.Format::date(date('Y-m-d')).'<br/>';
$actionText .= __('Total Count').': '.($sendReport['emailSent'] + $sendReport['emailFailed']).'<br/>';
$actionText .= __('Send Succeed Count').': '.$sendReport['emailSent'].'<br/>';
$actionText .= __('Send Fail Count').': '.$sendReport['emailFailed'];

$actionLink = '/index.php?q=/modules/Trip Planner/trips_manage.php';
$notificationSender->addNotification($session->get('organisationAdministrator'), $actionText, 'Trip Planner', $actionLink);
$notificationSender->sendNotifications();

// Output the result to terminal
echo sprintf('Sent %1$s emails: %2$s emails sent, %3$s emails failed.', $sendReport['emailSent'] + $sendReport['emailFailed'], $sendReport['emailSent'], $sendReport['emailFailed'])."\n";



