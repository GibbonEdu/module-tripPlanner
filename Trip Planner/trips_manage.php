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

use Gibbon\Forms\Form;

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    print "<div class='error'>";
        print "You do not have access to this action.";
    print "</div>";
} else {
    $highestAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);
    if ($highestAction != false) {
        print "<div class='trail'>";
            print "<div class='trailHead'><a href='" . $_SESSION[$guid]["absoluteURL"] . "'>" . _("Home") . "</a> > <a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_GET["q"]) . "/" . getModuleEntry($_GET["q"], $connection2, $guid) . "'>" . _(getModuleName($_GET["q"])) . "</a> > </div><div class='trailEnd'>" . _('Manage Trip Requests') . "</div>";
        print "</div>";

        if (isset($_GET['return'])) {
            returnProcess($guid, $_GET['return'], null, null);
        }

        $expenseApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");
        $riskAssessmentApproval = getSettingByScope($connection2, "Trip Planner", "riskAssessmentApproval");

        $ama = (isApprover($connection2, $_SESSION[$guid]["gibbonPersonID"]) && $expenseApprovalType == "Chain Of All") || ($riskAssessmentApproval && isApprover($connection2, $_SESSION[$guid]["gibbonPersonID"], true));
        $departments = getHOD($connection2, $_SESSION[$guid]["gibbonPersonID"]);
        $isHOD = $departments->rowCount() > 0;

        $data = array();
        $sql = "SELECT tripPlannerRequests.tripPlannerRequestID, tripPlannerRequests.timestampCreation, tripPlannerRequests.title, tripPlannerRequests.description, tripPlannerRequests.status, gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.gibbonPersonID FROM tripPlannerRequests LEFT JOIN gibbonPerson ON tripPlannerRequests.creatorPersonID = gibbonPerson.gibbonPersonID";
        $connector = " WHERE ";

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

        $eutFilter = getSettingByScope($connection2, "Trip Planner", "expiredUnapprovedFilter");

        //This must be the FIRST filter check!
        if ($relationFilter == "I") {
            $data["teacherPersonID"] = $_SESSION[$guid]["gibbonPersonID"];
            $sql .= " JOIN tripPlannerRequestPerson ON (tripPlannerRequestPerson.tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID) WHERE (tripPlannerRequestPerson.role='Teacher' AND :teacherPersonID = tripPlannerRequestPerson.gibbonPersonID OR teacherPersonIDs LIKE CONCAT('%', :teacherPersonID, '%'))";
            $connector = " AND ";
        } elseif ($relationFilter == "MR") {
            $data["creatorPersonID"] = $_SESSION[$guid]["gibbonPersonID"];
            $sql .= $connector . "tripPlannerRequests.creatorPersonID=:creatorPersonID";
            if ($connector == " WHERE ") {
                $connector = " AND ";
            }
        } elseif ($relationFilter == "AMA") {
            $statusFilter = "All";
        } elseif (strpos($relationFilter, "DR") !== false) {
            $data["gibbonDepartmentID"] = substr($relationFilter, 2);
            $sql .= $connector . ":gibbonDepartmentID IN (SELECT gibbonDepartmentID FROM gibbonDepartmentStaff WHERE gibbonPersonID = tripPlannerRequests.creatorPersonID)";
            if ($connector == " WHERE ") {
                $connector = " AND ";
            }
        }

        if ($statusFilter != "All") {
            $data["status"] = $statusFilter;
            $sql .= $connector . "tripPlannerRequests.status=:status";
            if ($connector == " WHERE ") {
                $connector = " AND ";
            }
        }

        if ($yearFilter != "All Years") {
            $data["gibbonSchoolYearID"] = $yearFilter;
            $sql .= $connector . "tripPlannerRequests.gibbonSchoolYearID=:gibbonSchoolYearID";
            if ($connector == " WHERE ") {
                $connector = " AND ";
            }
        }

        try {
            $sqlYear = "SELECT gibbonSchoolYearID, name FROM gibbonSchoolYear";
            $resultYear = $connection2->prepare($sqlYear);
            $resultYear->execute();
        } catch (PDOException $e) {
        }

        $years = array();

        while ($row = $resultYear->fetch()) {
            $years[$row['gibbonSchoolYearID']] = $row['name'];
        }

        print "<h3>";
            print __m("Filter");
        print "</h3>";

        $form = Form::create("tripFilters", $_SESSION[$guid]["absoluteURL"] . "/index.php?q=" . $_GET["q"]);

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

        try {
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
        }
        ?>

        <h3>
            Requests
        </h3>

        <div class="linkTop">
            <a style='position:relative; bottom:10px; float:right;' href='<?php print $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Trip Planner/trips_submitRequest.php" ?>'>
                <?php
                    print __m("Submit Request");
                ?>
                <img style='margin-left: -2px' title='<?php print __m("Sumbit") ?>' src='<?php print $_SESSION[$guid]["absoluteURL"] . "/themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/page_new.png" ?>'/>
            </a>
        </div>

        <table cellspacing = '0' style = 'width: 100% !important'>
            <tr>
                <th>
                    Title
                </th>
                <th>
                    Description
                </th>
                <th>
                    Owner
                </th>
                <th>
                    Status
                </th>
                <th>
                    Action
                </th>
            </tr>
        <?php
        if ($result->rowCount() == 0) {
            ?>
            <tr>
                <td colspan=5>
                    There are no records to display
                </td>
            </tr>
        <?php
        } else {
            $rowCount = 0;
            $descriptionLength = 100;
            while ($row = $result->fetch()) {
                $show = true;
                if ($relationFilter == "AMA" && $ama) {
                    if (!($row["status"] == "Requested" && needsApproval($connection2, $row["tripPlannerRequestID"], $_SESSION[$guid]["gibbonPersonID"])) == 0 && !($row["status"] == "Awaiting Final Approval" && isApprover($connection2, $_SESSION[$guid]["gibbonPersonID"], true))) {
                        $show = false;
                    }
                }

                if ($eutFilter) {
                    $startDate = getFirstDayOfTrip($connection2, $row["tripPlannerRequestID"]);
                    if (strtotime($startDate) < mktime(0, 0, 0) && $row["status"] != "Approved") {
                        $show = false;
                    }
                }
                if ($show) {
                    $class = "odd";
                    if ($rowCount % 2 == 0) {
                        $class = "even";
                    }
                    print "<tr class='$class'>";
                        print "<td style='width:20%'>" . $row['title'] . "</td>";
                        $descriptionText = strip_tags($row['description']);
                        if (strlen($descriptionText)>$descriptionLength) {
                            $descriptionText = substr($descriptionText, 0, $descriptionLength) . "...";
                        }
                        print "<td>" . $descriptionText . "</td>";
                        print "<td style='width:20%'>" . $row['preferredName'] . " " . $row["surname"] . "</td>";
                        print "<td style='width:12%'>";
                            print $row['status'] . "</br>";
                            //print "<span style='font-size: 85%; font-style: italic'>" . dateConvertBack($guid, $row['timestampCreation']) . "</span>";
                        print "</td>";
                        print "<td style='width:16.5%'>";
                            //TODO: Add duplicate function
                            print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID=" . $row["tripPlannerRequestID"] . "'><img title='" . _('View') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/plus.png'/></a> ";
                            if ($row["status"] != "Cancelled" && $row["status"] != "Rejected" && $row['gibbonPersonID'] == $_SESSION[$guid]['gibbonPersonID']) {
                                print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Trip Planner/trips_submitRequest.php&mode=edit&tripPlannerRequestID=" . $row["tripPlannerRequestID"] . "'><img title='" . _('Edit') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/config.png'/></a> ";
                            }
                            if (($row["status"] == "Requested" && needsApproval($connection2, $row["tripPlannerRequestID"], $_SESSION[$guid]["gibbonPersonID"])) == 0 || ($row["status"] == "Awaiting Final Approval" && isApprover($connection2, $_SESSION[$guid]["gibbonPersonID"], true))) {
                                print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Trip Planner/trips_requestApprove.php&tripPlannerRequestID=" . $row["tripPlannerRequestID"] . "'><img title='" . __m('Approve/Reject') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/iconTick.png'/></a> ";
                            }
                        print "</td>";
                    print "</tr>";
                    $rowCount++;
                }
            }

            if($rowCount == 0) {
                  ?>
                <tr>
                    <td colspan=5>
                        There are no records to display
                    </td>
                </tr>
            <?php
            }
        }
        ?>
        </table>
        <?php
    } else {
        print "<div class='error'>";
            print "Highest grouped action could not be determined.";
        print "</div>";
    }
}
?>
