<?php
function isOwner($connection2, $tripPlannerRequestID, $gibbonPersonID)
{
    try {
        $data = array("tripPlannerRequestID" => $tripPlannerRequestID, "gibbonPersonID" => $gibbonPersonID);
        $sql = "SELECT title FROM tripPlannerRequests WHERE tripPlannerRequestID=:tripPlannerRequestID AND creatorPersonID=:gibbonPersonID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    return ($result->rowCount() == 1);
}

function getOwner($connection2, $tripPlannerRequestID)
{
    try {
        $data = array("tripPlannerRequestID" => $tripPlannerRequestID);
        $sql = "SELECT creatorPersonID FROM tripPlannerRequests WHERE tripPlannerRequestID=:tripPlannerRequestID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    return $result->fetch()["creatorPersonID"];
}

function getApprovers($connection2)
{
    try {
        $sql = "SELECT tripPlannerApproverID, tripPlannerApprovers.gibbonPersonID, sequenceNumber FROM tripPlannerApprovers JOIN gibbonPerson ON tripPlannerApprovers.gibbonPersonID=gibbonPerson.gibbonPersonID ORDER BY ";
        $requestApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");
        if ($requestApprovalType == "Chain Of") {
            $sql .= "sequenceNumber, ";
        }
        $sql .= "surname, preferredName";
        $result = $connection2->prepare($sql);
        $result->execute();
    } catch (PDOException $e) {
    }

    return $result;
}

function getNameFromID($connection2, $gibbonPersonID)
{
    try {
        $data = array("gibbonPersonID" => $gibbonPersonID);
        $sql = "SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    return $result->fetch();
}

function getApprover($connection2, $tripPlannerApproverID)
{
    try {
        $data = array("tripPlannerApproverID" => $tripPlannerApproverID);
        $sql = "SELECT * FROM tripPlannerApprovers WHERE tripPlannerApproverID=:tripPlannerApproverID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    return $result->fetch();
}

function approverExists($connection2, $tripPlannerApproverID)
{
    $approver = getApprover($connection2, $tripPlannerApproverID);
    return ($approver->rowCount() == 1);
}

function isApprover($connection2, $gibbonPersonID)
{

    try {
        $data = array("gibbonPersonID" => $gibbonPersonID);
        $sql = "SELECT tripPlannerApproverID FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    return ($result->rowCount() == 1);
}

function needsApproval($connection2, $tripPlannerRequestID, $gibbonPersonID)
{
    if (isApprover($connection2, $gibbonPersonID)) {
        try {
            $data = array("tripPlannerRequestID" => $tripPlannerRequestID);
            $sql = "SELECT status FROM tripPlannerRequests WHERE tripPlannerRequestID=:tripPlannerRequestID";
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            return false;
        }
        $request = $result->fetch();
        if ($request["status"] == "Requested") {
            $requestApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");
            if ($requestApprovalType == "One Of") {
                return true;
            } elseif ($requestApprovalType == "Two Of") {
                $events = getEvents($connection2, $tripPlannerRequestID, array("Approval - Partial"));
                while ($event = $events->fetch()) {
                    if ($event["gibbonPersonID"] == $gibbonPersonID) {
                        return false;
                    }
                }
                return $events->rowCount() < 2;
            } elseif ($requestApprovalType == "Chain Of All") {
                //Get notifiers in sequence
                try {
                    $dataApprovers = array('tripPlannerRequestID' => $tripPlannerRequestID);
                    $sqlApprovers = "SELECT gibbonPerson.gibbonPersonID AS g1, tripPlannerRequestLog.gibbonPersonID AS g2 FROM tripPlannerApprovers JOIN gibbonPerson ON (tripPlannerApprovers.gibbonPersonID=gibbonPerson.gibbonPersonID) LEFT JOIN tripPlannerRequestLog ON (tripPlannerRequestLog.gibbonPersonID=tripPlannerApprovers.gibbonPersonID AND tripPlannerRequestLog.action='Approval - Partial' AND tripPlannerRequestLog.tripPlannerRequestID=:tripPlannerRequestID) WHERE gibbonPerson.status='Full' ORDER BY sequenceNumber, surname, preferredName";
                    $resultApprovers = $connection2->prepare($sqlApprovers);
                    $resultApprovers->execute($dataApprovers);
                } catch (PDOException $e) {
                    return false;
                }
                if ($resultApprovers->rowCount() < 1) {
                    return false;
                } else {
                    $approvers = $resultApprovers->fetchAll();
                    $gibbonPersonIDNext = null;
                    foreach ($approvers as $approver) {
                        if ($approver['g1'] != $approver['g2']) {
                            if (is_null($gibbonPersonIDNext)) {
                                $gibbonPersonIDNext = $approver['g1'];
                                break;
                            }
                        }
                    }

                    if (is_null($gibbonPersonIDNext)) {
                        return false;
                    } else {
                        return $gibbonPersonIDNext == $gibbonPersonID;
                    }
                }
            }
        }
    }
    return false;
}

function getHOD($connection2, $gibbonPersonID)
{
    try {
        $data = array("gibbonPersonID" => $gibbonPersonID);
        $sql = "SELECT gibbonDepartmentID, nameShort FROM gibbonDepartment WHERE gibbonDepartmentID IN (SELECT gibbonDepartmentID FROM gibbonDepartmentStaff WHERE gibbonPersonID=:gibbonPersonID AND role='Coordinator')";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    return $result;
}

function getDepartments($connection2, $gibbonPersonID)
{
    try {
        $data = array("gibbonPersonID" => $gibbonPersonID);
        $sql = "SELECT gibbonDepartmentID FROM gibbonDepartmentStaff WHERE gibbonPersonID=:gibbonPersonID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    $departments = array();

    while ($row = $result->fetch()) {
        $departments[] = $row["gibbonDepartmentID"];
    }

    return $departments;
}

function isInvolved($connection2, $tripPlannerRequestID, $gibbonPersonID)
{
    try {
        $data = array("tripPlannerRequestID" => $tripPlannerRequestID, "gibbonPersonID" => $gibbonPersonID);
        $sql = "SELECT teacherPersonIDs FROM tripPlannerRequests WHERE tripPlannerRequestID=:tripPlannerRequestID AND teacherPersonIDs LIKE CONCAT('%', :gibbonPersonID, '%')";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    return ($result->rowCount() == 1);
}

function logEvent($connection2, $tripPlannerRequestID, $gibbonPersonID, $action, $comment = null)
{
    if ($tripPlannerRequestID != null && $gibbonPersonID != null && $action != null)
    {
        try {
            $date = new DateTime();
            $data = array("tripPlannerRequestID" => $tripPlannerRequestID, "gibbonPersonID" => $gibbonPersonID, "action" => $action, "comment" => $comment, "timestamp" => $date->format('Y-m-d H:i:s'));
            $sql = "INSERT INTO tripPlannerRequestLog SET tripPlannerRequestID=:tripPlannerRequestID, gibbonPersonID=:gibbonPersonID, action=:action, comment=:comment, timestamp=:timestamp";
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            return false;
        }
    }
    return true;
}

function getEvents($connection2, $tripPlannerRequestID, $actions=array())
{
    if ($connection2 != null && $tripPlannerRequestID != null) {
        try {
            $data = array("tripPlannerRequestID" => $tripPlannerRequestID);
            $sql = "SELECT tripPlannerRequestLogID, gibbonPersonID, action, comment, timestamp FROM tripPlannerRequestLog WHERE tripPlannerRequestID=:tripPlannerRequestID";
            if (count($actions) > 0 && is_array($actions) == true) {
                $sql .= " AND (";
                for ($i = 0; $i < count($actions); $i++) {
                    $action = $actions[$i];
                    if ($i > 0) {
                        $sql .= " OR ";
                    }
                    $data["action$i"] = $action;
                    $sql .= "action=:action" . $i;
                }
                $sql .= ")";
            }
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
        }
        return $result;
    }
}

function getRequestLog($guid, $connection2, $tripPlannerRequestID, $commentsOpen = false)
{
    try {
        $data = array('tripPlannerRequestID' => $tripPlannerRequestID);
        $sql = 'SELECT tripPlannerRequestLog.*, surname, preferredName FROM tripPlannerRequests JOIN tripPlannerRequestLog ON (tripPlannerRequestLog.tripPlannerRequestID=tripPlannerRequests.tripPlannerRequestID) JOIN gibbonPerson ON (tripPlannerRequestLog.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE tripPlannerRequestLog.tripPlannerRequestID=:tripPlannerRequestID ORDER BY timestamp';
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        echo "<div class='error'>".$e->getMessage().'</div>';
    }

    if ($result->rowCount() < 1) {
        echo "<div class='error'>";
            echo __($guid, 'There are no records to display.');
        echo '</div>';
    } else {
        echo "<table cellspacing='0' style='width: 100%'>";
            echo "<tr class='head'>";
                echo '<th>';
                    echo __($guid, 'Person');
                echo '</th>';
                echo '<th>';
                    echo __($guid, 'Date');
                echo '</th>';
                echo '<th>';
                    echo __($guid, 'Event');
                echo '</th>';
                if ($commentsOpen == false) {
                    echo '<th>';
                        echo __($guid, 'Actions');
                    echo '</th>';
                }
            echo '</tr>';

            $rowNum = 'odd';
            $count = 0;
            while ($row = $result->fetch()) {
                if ($count % 2 == 0) {
                    $rowNum = 'even';
                } else {
                    $rowNum = 'odd';
                }
                ++$count;

                //COLOR ROW BY STATUS!
                echo "<tr class=$rowNum>";
                    echo '<td>';
                        echo formatName('', $row['preferredName'], $row['surname'], 'Staff', false, true);
                    echo '</td>';
                    echo '<td>';
                        echo dateConvertBack($guid, substr($row['timestamp'], 0, 10));
                    echo '</td>';
                    echo '<td>';
                        echo $row['action'];
                    echo '</td>';
                    if ($commentsOpen == false) {
                        echo '<td>';
                            echo "<script type='text/javascript'>";
                                echo '$(document).ready(function(){';
                                    echo "\$(\".comment-$count\").hide();";
                                    echo "\$(\".show_hide-$count\").fadeIn(500);";
                                    echo "\$(\".show_hide-$count\").click(function(){";
                                        echo "\$(\".comment-$count\").fadeToggle(500);";
                                    echo '});';
                                echo '});';
                            echo '</script>';
                            if ($row['comment'] != '') {
                                echo "<a title='".__($guid, 'View Description')."' class='show_hide-$count' onclick='false' href='#'><img style='padding-right: 5px' src='".$_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName']."/img/page_down.png' alt='".__($guid, 'Show Comment')."' onclick='return false;' /></a>";
                            }
                        echo '</td>';
                    }
                echo '</tr>';
                if ($row['comment'] != '') {
                    echo "<tr class='comment-$count' id='comment-$count'>";
                        echo '<td colspan=4>';
                        if ($row['comment'] != '') {
                            echo nl2brr($row['comment']).'<br/><br/>';
                        }
                        echo '</td>';
                    echo '</tr>';
                }
            }
        echo '</table>';
    }
}

function getPersonBlock($guid, $connection2, $gibbonPersonID, $role)
{
    try {
        $data = array('gibbonPersonID' => $gibbonPersonID);
        $sql = 'SELECT title, surname, preferredName, image_240 FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID';
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        // echo "<div class='error'>".$e->getMessage().'</div>';
    }

    if ($result->rowCount() == 1) {
        $row = $result->fetch();
        print "<td style='border: 1px solid #rgba (1,1,1,0); width:20%; text-align: center; vertical-align: top'>";
            print "<div>";
                print getUserPhoto($guid, $row['image_240'], 75);
            print "</div>";
            print "<div><b>";
                print formatName($row['title'], $row['preferredName'], $row['surname'], $role);
            print "</b><br/></div>";
        print "</td>";
    }
}

function requestNotification($guid, $connection2, $tripPlannerRequestID, $action)
{
    $message = __($guid, 'Someone has commented on your trip request.');
    if ($action == "Approved") {
        $message = __($guid, 'Your trip request has been fully approved.');
    } elseif ($action == "Rejected") {
        $message = __($guid, 'Your trip request has been rejected.');
    }
    
    setNotification($connection2, $guid, getOwner($connection2, $tripPlannerRequestID), $message, 'Trip Planner', "/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID=$tripPlannerRequestID");
}

function makeCostBlock($guid, $connection2, $i, $outerBlock = TRUE)
{
    if ($outerBlock) {
        print "<div id='blockOuter$i' class='blockOuter'>";
    }
    ?>
        <script type="text/javascript">
            $(document).ready(function(){
                $("#blockInner<?php print $i ?>").css("display","none");
                $("#block<?php print $i ?>").css("height","72px")

                //Block contents control
                $('#show<?php print $i ?>').unbind('click').click(function() {
                    if ($("#blockInner<?php print $i ?>").is(":visible")) {
                        $("#blockInner<?php print $i ?>").css("display","none");
                        $("#block<?php print $i ?>").css("height","72px")
                        $('#show<?php echo $i ?>').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/plus.png' ?>')");
                    } else {
                        $("#blockInner<?php print $i ?>").slideDown("fast", $("#blockInner<?php print $i ?>").css("display","table-row"));
                        $("#block<?php print $i ?>").css("height","auto")
                        $('#show<?php echo $i ?>').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/minus.png' ?>')");
                    }
                });

                var nameClick<?php print $i ?> = false;
                $('#name<?php print $i ?>').focus(function() {
                    if (nameClick<?php print $i ?> == false) {
                        $('#name<?php print $i ?>').css("color", "#000");
                        $('#name<?php print $i ?>').val("");
                        nameClick<?php print $i ?> = true;
                    }
                });

                var costClick<?php print $i ?> = false;
                $('#cost<?php print $i ?>').focus(function() {
                    if (costClick<?php print $i ?> == false) {
                        $('#cost<?php print $i ?>').css("color", "#000");
                        $('#cost<?php print $i ?>').val("");
                        costClick<?php print $i ?> = true;
                    }
                });

                $('#delete<?php print $i ?>').unbind('click').click(function() {
                    if (confirm("Are you sure you want to delete this record?")) {
                        cost<?php print $i ?>.destroy();
                        $('#blockOuter<?php print $i ?>').fadeOut(600, function(){ 
                            $('#block<?php print $i ?>').remove(); 
                            $('#costOuter<?php print $i ?>').remove();
                            if ($('#cost').children().length == 1) {
                                $("#costOuter0").css("display", "block");
                            }
                        }); 
                    }
                });
            });
        </script>
        <div class='hiddenReveal' style='border: 1px solid #d8dcdf; margin: 0 0 5px' id="block<?php print $i ?>" style='padding: 0px'>
            <table class='blank' cellspacing='0' style='width: 100%'>
                <tr>
                    <td style='width: 70%'>
                        <input name='order[]' type='hidden' value='<?php print $i ?>'>
                        <input maxlength=100 id='name<?php print $i ?>' name='name<?php print $i ?>' type='text' style='float: none; border: 1px dotted #aaa; background: none; margin-left: 3px; <?php print "color: #999;" ?> margin-top: 0px; font-size: 140%; font-weight: bold; width: 350px' value='<?php print __($guid, "Cost Name") . " $i"; ?>'><br/>
                        <input maxlength=13 id='cost<?php print $i ?>' name='cost<?php print $i ?>' type='text' style='float: none; border: 1px dotted #aaa; background: none; margin-left: 3px; <?php print "color: #999;" ?> margin-top: 2px; font-size: 110%; font-style: italic; width: 95px' value='<?php print __($guid, "Value"); if ($_SESSION[$guid]["currency"]!="") { print " (" . $_SESSION[$guid]["currency"] . ")";} ?>'>
                        <script type="text/javascript">
                            var cost<?php print $i ?> = new LiveValidation('cost<?php print $i ?>');
                            cost<?php print $i ?>.add(Validate.Presence);
                            cost<?php print $i ?>.add( Validate.Format, { pattern: /^(?:\d*\.\d{1,2}|\d+)$/, failureMessage: "Invalid number format!" } );
                        </script>
                    </td>
                    <td style='text-align: right; width: 30%'>
                        <div style='margin-bottom: 5px'>
                            <?php
                                print "<img id='delete$i' title='" . __($guid, 'Delete') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/garbage.png'/> ";
                                print "<div id='show$i'  title='" . __($guid, 'Show/Hide') . "' style='margin-top: -1px; margin-left: 3px; padding-right: 1px; float: right; width: 24px; height: 25px; background-image: url(\"" . $_SESSION[$guid]["absoluteURL"] . "/themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/plus.png\")'></div></br>";
                            ?>
                        </div>
                    </td>
                </tr>
                <tr id="blockInner<?php print $i ?>">
                    <td colspan=2 style='vertical-align: top'>
                        <?php
                            print "<div style='text-align: left; font-weight: bold; margin-top: 5px; margin-left: 0.4%'>Description</div>";
                            print "<textarea style='width: 99.2%; resize:vertical;' name='description" . $i . "'>" . htmlPrep("") . "</textarea>";
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    <?php
    if ($outerBlock) {
        print "</div>";
    }
}

function getPastTrips($connection2, $tripPlannerRequestID, $gibbonPersonID)
{
    try {
        $data = array("gibbonPersonID" => $gibbonPersonID, "tripPlannerRequestID" => $tripPlannerRequestID);
        $sql = "SELECT tripPlannerRequestID, date, startTime, endTime FROM tripPlannerRequests WHERE NOT tripPlannerRequestID=:tripPlannerRequestID AND status='Approved' AND gibbonSchoolYearID=(SELECT gibbonSchoolYearID FROM tripPlannerRequests WHERE tripPlannerRequestID=:tripPlannerRequestID) AND (teacherPersonIDs LIKE CONCAT('%', :gibbonPersonID, '%') OR studentPersonIDs LIKE CONCAT('%', :gibbonPersonID, '%'))";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }   

    return $result;
}

function getPlannerOverlaps($connection2, $date, $startTime, $endTime, $gibbonPersonID)
{
    try {
        $data = array("gibbonPersonID" => $gibbonPersonID, "date" => $date, "startTime" => $startTime, "endTime" => $endTime);
        $sql = "SELECT gibbonPlannerEntryID, gibbonCourseClassID FROM gibbonPlannerEntry WHERE date=:date AND (timeStart < :endTime OR timeEnd > :startTime) AND gibbonCourseClassID IN (SELECT gibbonCourseClassID FROM gibbonCourseClassPerson WHERE gibbonPersonID=:gibbonPersonID AND role='Student')";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }   

    return $result;
}
?>
