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
include "./modules/" . $_SESSION[$guid]["module"] . "/moduleFunctions.php";

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_editApprover.php')) {
    //Acess denied
    print "<div class='error'>";
        print "You do not have access to this action.";
    print "</div>";
} else {

    print "<div class='trail'>";
        print "<div class='trailHead'><a href='" . $_SESSION[$guid]["absoluteURL"] . "'>" . _("Home") . "</a> > <a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_GET["q"]) . "/" . getModuleEntry($_GET["q"], $connection2, $guid) . "'>" . _(getModuleName($_GET["q"])) . "</a> > <a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/trips_manageApprovers.php'>" . _("Manage Approver") . "</a> > </div><div class='trailEnd'>" . _('Edit Approver') . "</div>";
    print "</div>";

    if (isset($_GET["tripPlannerApproverID"])) {
        if ($_GET["tripPlannerApproverID"] != null && $_GET["tripPlannerApproverID"] != "") {
            $tripPlannerApproverID = $_GET["tripPlannerApproverID"];
        }
    }

    $approver = getApprover($connection2, $tripPlannerApproverID);

    print "<h3>";
        print "Edit Approver";
    print "</h3>";

    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    ?>
    <form method="post" action="<?php print $_SESSION[$guid]["absoluteURL"] . "/modules/" . $_SESSION[$guid]["module"] . "/trips_editApproverProcess.php?tripPlannerApproverID=$tripPlannerApproverID" ?>">
        <table class='smallIntBorder' cellspacing='0' style="width: 100%">  
            <tr>
                <td> 
                    <b><?php print _('Staff') ?> *</b><br/>
                </td>
                <td class="right">
                    <select name="gibbonPersonID" id="gibbonPersonID" style="width: 302px">
                        <option value="Please select..."><?php print _('Please select...') ?></option>
                        <?php
                        try {
                            $sqlSelect = "SELECT * FROM gibbonPerson JOIN gibbonStaff ON (gibbonPerson.gibbonPersonID=gibbonStaff.gibbonPersonID) WHERE status='Full' ORDER BY surname, preferredName";
                            $resultSelect = $connection2->prepare($sqlSelect);
                            $resultSelect->execute();
                        } catch (PDOException $e) {
                        }

                        while ($rowSelect = $resultSelect->fetch()) {
                            $selected = "";
                            if ($rowSelect["gibbonPersonID"] == $approver["gibbonPersonID"]) {
                                $selected = "selected";
                            }

                            if (!isApprover($connection2, $rowSelect["gibbonPersonID"]) || $selected == "selected") {
                                print "<option value='" . $rowSelect["gibbonPersonID"] . "' $selected>" . formatName("", htmlPrep($rowSelect["preferredName"]), htmlPrep($rowSelect["surname"]), "Staff", true, true) . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <script type="text/javascript">
                        var gibbonPersonID=new LiveValidation('gibbonPersonID');
                        gibbonPersonID.add(Validate.Exclusion, { within: ['Please select...'], failureMessage: "<?php print _('Select something!') ?>"});
                    </script>
                </td>
            </tr>
            <?php
            $expenseApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");
            if ($expenseApprovalType == "Chain Of All") {
                ?>
                <tr>
                    <td> 
                        <b><?php print _('Sequence Number') ?> *</b><br/>
                        <span style="font-size: 90%"><i><?php print _('Must be unique.') ?></i></span>
                    </td>
                    <td class="right">
                        <input name="sequenceNumber" ID="sequenceNumber" value="<?php print $approver['sequenceNumber']; ?>" type="text" style="width: 300px">
                        <script type="text/javascript">
                            var sequenceNumber=new LiveValidation('sequenceNumber');
                            sequenceNumber.add(Validate.Numericality, { minimum: 0 } );
                            sequenceNumber.add(Validate.Presence);
                        </script>
                    </td>
                </tr>
                <?php
            }
            ?>
            <tr>
                <td>
                    <span style="font-size: 90%"><i>* <?php print _("denotes a required field"); ?></i></span>
                </td>
                <td class="right">
                    <input type="hidden" name="address" value="<?php print $_SESSION[$guid]["address"] ?>">
                    <input type="submit" value="<?php print _("Submit"); ?>">
                </td>
            </tr>
        </table>
    </form>
    <?php
}   
?>
