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

@session_start();

//Module includes
include "./modules/Trip Planner/moduleFunctions.php";

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    //Acess denied
    print "<div class='error'>";
        print "You do not have access to this action.";
    print "</div>";
} else {
    $highestAction = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_manage.php', $connection2);
    if($highestAction != false) {
        print "<div class='trail'>";
            print "<div class='trailHead'><a href='" . $_SESSION[$guid]["absoluteURL"] . "'>" . _("Home") . "</a> > <a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_GET["q"]) . "/" . getModuleEntry($_GET["q"], $connection2, $guid) . "'>" . _(getModuleName($_GET["q"])) . "</a> > </div><div class='trailEnd'>" . _('Manage Trip Requests') . "</div>";
        print "</div>";

        if (isset($_GET['return'])) {
            returnProcess($guid, $_GET['return'], null, null);
        }

        $expenseApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");

        $ama = isApprover($connection2, $_SESSION[$guid]["gibbonPersonID"]) && $expenseApprovalType == "Chain Of All";
        $rf = $highestAction == "Manage Trips_all" || isApprover($connection2, $_SESSION[$guid]["gibbonPersonID"]);

        $data = array();
        $sql = "SELECT tripPlannerRequests.tripPlannerRequestID, tripPlannerRequests.timestampCreation, tripPlannerRequests.title, tripPlannerRequests.description, tripPlannerRequests.status, gibbonPerson.preferredName, gibbonPerson.surname FROM tripPlannerRequests JOIN gibbonPerson ON tripPlannerRequests.creatorPersonID = gibbonPerson.gibbonPersonID";
        $connector = " WHERE ";

        $statusFilter = "Requested";
        $yearFilter = $_SESSION[$guid]["gibbonSchoolYearID"];
        $relationFilter = "My Requests";

        if (isset($_POST["statusFilter"])) {
            $statusFilter = $_POST["statusFilter"];
        }

        if (isset($_POST["yearFilter"])) {
            $yearFilter = $_POST["yearFilter"];
        }

        if (isset($_POST["relationFilter"])) {
            if($rf) {
                $relationFilter = $_POST["relationFilter"];
            }
        }

        if ($relationFilter == "My Requests") {
            $data["creatorPersonID"] = $_SESSION[$guid]["gibbonPersonID"];
            $sql .= $connector . "tripPlannerRequests.creatorPersonID=:creatorPersonID";
            if ($connector == " WHERE ") {
                $connector = " AND ";
            }
        } elseif($relationFilter == "Awaiting My Approval" && $ama) {
            $statusFilter = "Requested";
        }

        if($statusFilter != "All") {
            $data["status"] = $statusFilter;
            $sql .= $connector . "tripPlannerRequests.status=:status";
            if($connector == " WHERE ") {
                $connector = " AND ";
            }
        }

        if($yearFilter != "All Years") {
            $data["gibbonSchoolYearID"] = $yearFilter;
            $sql .= $connector . "tripPlannerRequests.gibbonSchoolYearID=:gibbonSchoolYearID";
            if($connector == " WHERE ") {
                $connector = " AND ";
            }
        }

        ?>
        <h3>
            Filter
        </h3>
        <?php
        print "<form method='post' action='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=" . $_GET["q"] . "'>"; ?>
            <table class='noIntBorder' cellspacing='0' style='width: 100%'>
                <tr>
                    <td> 
                        <b><?php echo __($guid, 'Status Filter') ?> *</b><br/>
                    </td>
                    <td class="right">
                        <?php
                        $statuses = array("Requested", "Approved", "Rejected", "Cancelled", "All");
                        echo "<select name='statusFilter' id='statusFilter' style='width:302px'>";
                            foreach($statuses as $status) {
                                $selected = "";
                                if($status == $statusFilter) {
                                    $selected = "selected";
                                }
                                echo "<option $selected value='$status'>".__($guid, $status).'</option>';
                            }
                        echo '</select>';
                        ?>
                    </td>
                </tr>
                <?php
                if ($rf) {
                    ?>
                    <tr>
                        <td> 
                            <b><?php echo __($guid, 'Relation Filter') ?> *</b><br/>
                        </td>
                        <td class="right">
                            <?php
                            $relations = array("All", "My Requests");
                            if ($ama) {
                                $relations[] = "Awaiting My Approval";
                            }
                            echo "<select name='relationFilter' id='relationFilter' style='width:302px'>";
                                foreach($relations as $relation) {
                                    $selected = "";
                                    if($relation == $relationFilter) {
                                        $selected = "selected";
                                    }
                                    echo "<option $selected value='$relation'>".__($guid, $relation).'</option>';
                                }
                            echo '</select>';
                            ?>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                <tr>
                    <td>
                        <b><?php echo __($guid, 'Year Filter') ?> *</b><br/>
                    </td>
                    <td class="right">
                        <select name='yearFilter' id='yearFilter' style='width:302px'>
                            <option value='All Years'>All Years</option>
                            <?php
                            try {
                                $sqlYear = "SELECT gibbonSchoolYearID, name FROM gibbonSchoolYear";
                                $resultYear = $connection2->prepare($sqlYear);
                                $resultYear->execute();
                            } catch (PDOException $e) {
                            }

                            while($row = $resultYear->fetch()) {
                                $selected = "" ;
                                if ($row['gibbonSchoolYearID'] == $yearFilter) {
                                    $selected = "selected" ;
                                }
                                print "<option $selected value='" . $row['gibbonSchoolYearID'] . "'>". $row['name'] ."</option>" ;
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class='right' colspan=2>
                        <input type='submit' value='<?php print _('Go') ?>'>
                    </td>
                </tr>
            </table>
        </form>

        <?php
            try {
                $result = $connection2->prepare($sql);
                $result->execute($data);
            } catch (PDOException $e) {
            }
        ?>

        <h3>
            Requests
        </h3>
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
                    Status<br/>
                    <span style='font-size: 85%; font-style: italic'><?php print _('Date'); ?></span>
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
            while ($row = $result->fetch()) {
                $show = true;
                if ($relationFilter == "Awaiting My Approval" && $ama) {
                    if (!needsApproval($connection2, $row["tripPlannerRequestID"], $_SESSION[$guid]["gibbonPersonID"])) {
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
                        print "<td>" . $row['description'] . "</td>";
                        print "<td style='width:20%'>" . $row['preferredName'] . " " . $row["surname"] . "</td>";
                        print "<td style='width:12%'>";
                            print $row['status'] . "</br>";
                            print "<span style='font-size: 85%; font-style: italic'>" . dateConvertBack($guid, $row['timestampCreation']) . "</span>";          
                        print "</td>";
                        print "<td style='width:16.5%'>";
                            print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/trips_requestView.php&tripPlannerRequestID=" . $row["tripPlannerRequestID"] . "'><img title='" . _('View') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/plus.png'/></a> ";
                            if ($row["status"] != "Cancelled" && $row["status"] != "Rejected") {
                                print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/trips_requestEdit.php&tripPlannerRequestID=" . $row["tripPlannerRequestID"] . "'><img title='" . _('Edit') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/config.png'/></a> ";
                            }
                            if ($row["status"] == "Requested") {
                                if (needsApproval($connection2, $row["tripPlannerRequestID"], $_SESSION[$guid]["gibbonPersonID"])) {
                                    print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/trips_requestApprove.php&tripPlannerRequestID=" . $row["tripPlannerRequestID"] . "'><img title='" . __($guid, 'Approve/Reject') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/iconTick.png'/></a> ";
                                }
                            }
                        print "</td>";
                    print "</tr>";
                    $rowCount++;
                }
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
