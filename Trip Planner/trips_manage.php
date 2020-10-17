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

include "./modules/Trip Planner/moduleFunctions.php";
include "./modules/Trip Planner/src/Domain/TripPlanner/TripGateway.php";

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
#use Gibbon\Module\TripPlanner\Domain;
use Gibbon\Domain\School\SchoolYearGateway;

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    print "<div class='error'>";
        print "You do not have access to this action.";
    print "</div>";
} else {
    $highestAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);
    if ($highestAction != false) {
        $page->breadcrumbs->add(__('Manage Trip Requests'));

        if (isset($_GET['return'])) {
            returnProcess($guid, $_GET['return'], null, null);
        }

        $expenseApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");
        $riskAssessmentApproval = getSettingByScope($connection2, "Trip Planner", "riskAssessmentApproval");
        $eutFilter = getSettingByScope($connection2, "Trip Planner", "expiredUnapprovedFilter");

        $ama = (isApprover($connection2, $_SESSION[$guid]["gibbonPersonID"]) && $expenseApprovalType == "Chain Of All") || ($riskAssessmentApproval && isApprover($connection2, $_SESSION[$guid]["gibbonPersonID"], true));
        $departments = getHOD($connection2, $_SESSION[$guid]["gibbonPersonID"]);
        $isHOD = $departments->rowCount() > 0;

        $relations = array();
        $relationFilter = "MR";

        if ($highestAction == "Manage Trips_full") {
            $relations[""] = "All Requests";
            $relationFilter = "";
        }

        $relations["MR"] = "My Requests";
        $relations["I"] = "Involved";

        if ($isHOD) {
            while ($department = $departments->fetch()) {
                $relations["DR" . $department["gibbonDepartmentID"]] = "Department Requests - " . $department["nameShort"];
            }
        }

        if ($ama) {
            $relations["AMA"] = "Awaiting My Approval";
            $relationFilter = "AMA";
        }

        $statusFilter = "Requested";
        $yearFilter = $_SESSION[$guid]["gibbonSchoolYearID"];

        if (isset($_POST["statusFilter"])) {
            $statusFilter = $_POST["statusFilter"];
        }

        if (isset($_POST["yearFilter"])) {
            $yearFilter = $_POST["yearFilter"];
        }

        if (isset($_POST["relationFilter"])) {
            $relationFilter = $_POST["relationFilter"];
        }

        $schoolYearGateway = $container->get(SchoolYearGateway::class);
        foreach ($schoolYearGateway->querySchoolYears($schoolYearGateway->newQueryCriteria())->toArray() as $year) {
            $years[$year['gibbonSchoolYearID']] = $year['name'];
        }

        $form = Form::create("tripFilters", $_SESSION[$guid]["absoluteURL"] . "/index.php?q=" . $_GET["q"]);

        $form->setTitle(__("Filter"));

        $row = $form->addRow();
            $row->addLabel("statusFilterLabel", "Status Filter");
            $row->addSelect("statusFilter")->fromArray(array("All", "Requested", "Approved", "Rejected", "Cancelled", "Awaiting Final Approval"))->selected($statusFilter);

        $row = $form->addRow();
            $row->addLabel("relationFilterLabel", "Relation Filter");
            $row->addSelect("relationFilter")->fromArray($relations)->selected($relationFilter);

        $row = $form->addRow();
            $row->addLabel("yearFilterLabel", "Year Filter");
            $row->addSelect("yearFilter")->fromArray($years)->selected($yearFilter);

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        print $form->getOutput();
        
        $tripGateway = $container->get(TripGateway::class);
        $criteria = $tripGateway->newQueryCriteria(true)
              ->filterBy('status', $statusFilter)
              ->filterBy('relation', $relationFilter.':'.$gibbon->session->get('gibbonPersonID'))
              ->filterBy('year')
             ->filterBy('eutfilter', $eutFilter)
          ->fromPOST();

        $trips = $tripGateway->queryTrips($criteria);

        $table = DataTable::createPaginated('trips', $criteria);
        $table->setTitle(__("Requests"));
        $table
          ->addHeaderAction('add', __('Submit Request'))
          ->setURL('/modules/Trip Planner/trips_submitRequest.php');
        $table->addColumn('tripTitle', __('Title'));
        $table->addColumn('description', __('Description'));
        $table
          ->addColumn('owner', __('Owner'))
          ->format(function ($row) {
            return Format::name($row['title'], $row['preferredName'], $row['surname']);
          });
        $table->addColumn('status', __('Status'));
        $table
          ->addActionColumn()
          ->addParam('tripPlannerRequestID')
          ->format(function ($row, $actions) use ($connection2, $gibbon) {
              $actions
                ->addAction('view', __('View Details'))
                ->setURL('/modules/Trip Planner/trips_requestView.php');

            if ($gibbon->session->get('gibbonPersonID') == $row['creatorPersonID'] &&
                  $row['status'] != 'Cancelled' &&
                  $row['status'] != 'Rejected'
              ) {
                $actions
                ->addAction('edit', __('Edit'))
                ->addParam('mode', 'edit')
                ->setURL('/modules/Trip Planner/trips_submitRequest.php');
            }

            if (isApprover($connection2, $gibbon->session->get('gibbonPersonID'))) {
                $actions
                ->addAction('approve', __('Approve/Reject'))
                ->setURL('/modules/Trip Planner/trips_requestApprove.php')
                ->setIcon('iconTick');
            }
          });
          echo $table->render($trips);
    }
}
