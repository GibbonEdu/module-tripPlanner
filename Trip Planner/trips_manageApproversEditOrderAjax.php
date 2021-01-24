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

use Gibbon\Module\TripPlanner\Domain\ApproverGateway;

$_POST['address'] = '/modules/Trip Planner/trips_manageApprovers.php';

require_once '../../gibbon.php';

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manageApprovers.php')) {
    exit();
} else {
    // Proceed!
    $data = $_POST['data'] ?? [];
    $order = json_decode($_POST['order']);

    if (empty($order)) {
        exit();
    } else {
        $approverGateway = $container->get(ApproverGateway::class);
        $approverGateway->updateSequence($order); 
    }
}
