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
        $sql = "SELECT tripPlannerApproverID, tripPlannerApprovers.gibbonPersonID, sequenceNumber, finalApprover FROM tripPlannerApprovers JOIN gibbonPerson ON tripPlannerApprovers.gibbonPersonID=gibbonPerson.gibbonPersonID ORDER BY ";
        $requestApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");
        if ($requestApprovalType == "Chain Of All") {
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
        if($result->rowCount() == 1) {
            return $result->fetch();
        }
    } catch (PDOException $e) {
    }

    return null;
}

function approverExists($connection2, $tripPlannerApproverID)
{
    $approver = getApprover($connection2, $tripPlannerApproverID);
    return $approver != null;
}

function isApprover($connection2, $gibbonPersonID, $final=false)
{

    try {
        $data = array("gibbonPersonID" => $gibbonPersonID);
        $sql = "SELECT tripPlannerApproverID, finalApprover FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }
    if($result->rowCount() == 1) {
        return $result->fetch()["finalApprover"] || !$final;
    }
    return false;
}

/*Return Values:

0: Needs Approval
1: Databse Error
2: No permission
3: Already Approved.
4: Can't approve yet. (TODO)
5: Already Approved by you.

*/
function needsApproval($connection2, $tripPlannerRequestID, $gibbonPersonID)
{
    if (isApprover($connection2, $gibbonPersonID)) {
        try {
            $data = array("tripPlannerRequestID" => $tripPlannerRequestID);
            $sql = "SELECT status FROM tripPlannerRequests WHERE tripPlannerRequestID=:tripPlannerRequestID";
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            return 1;
        }
        $request = $result->fetch();
        if ($request["status"] == "Requested") {
            $requestApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");
            if ($requestApprovalType == "One Of") {
                return 0;
            } elseif ($requestApprovalType == "Two Of") {
                $events = getEvents($connection2, $tripPlannerRequestID, array("Approval - Partial"));
                while ($event = $events->fetch()) {
                    if ($event["gibbonPersonID"] == $gibbonPersonID) {
                        return 5;
                    }
                }
                if($events->rowCount() < 2) {
                    return 0;
                } else {
                    return 3;
                }
            } elseif ($requestApprovalType == "Chain Of All") {
                //Get notifiers in sequence
                try {
                    $dataApprovers = array('tripPlannerRequestID' => $tripPlannerRequestID);
                    $sqlApprovers = "SELECT gibbonPerson.gibbonPersonID AS g1, tripPlannerRequestLog.gibbonPersonID AS g2 FROM tripPlannerApprovers JOIN gibbonPerson ON (tripPlannerApprovers.gibbonPersonID=gibbonPerson.gibbonPersonID) LEFT JOIN tripPlannerRequestLog ON (tripPlannerRequestLog.gibbonPersonID=tripPlannerApprovers.gibbonPersonID AND tripPlannerRequestLog.action='Approval - Partial' AND tripPlannerRequestLog.tripPlannerRequestID=:tripPlannerRequestID) WHERE gibbonPerson.status='Full' ORDER BY sequenceNumber, surname, preferredName";
                    $resultApprovers = $connection2->prepare($sqlApprovers);
                    $resultApprovers->execute($dataApprovers);
                } catch (PDOException $e) {
                    return 1;
                }
                if ($resultApprovers->rowCount() == 0) {
                    return 1;
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
                        return 1;
                    } else if ($gibbonPersonIDNext == $gibbonPersonID) {
                        return 0;
                    }
                }
            }
        } elseif($request["status"] == "Approved") {
            return 3;
        }
    }
    return 2;
}

function getTripStatus($connection2, $tripPlannerRequestID) {
    try {
        $data = array("tripPlannerRequestID" => $tripPlannerRequestID);
        $sql = "SELECT status FROM tripPlannerRequests WHERE tripPlannerRequestID=:tripPlannerRequestID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        if($result->rowCount() == 1) {
            return $result->fetch()["status"];
        }
    } catch(PDOException $e) {
    }
    return null;
}

function getTrip($connection2, $tripPlannerRequestID) {
    try {
        $data = array("tripPlannerRequestID" => $tripPlannerRequestID);
        $sql = "SELECT creatorPersonID, timestampCreation, title, description, teacherPersonIDs, studentPersonIDs, location, date, endDate, startTime, endTime, riskAssessment, letterToParents, status FROM tripPlannerRequests WHERE tripPlannerRequestID=:tripPlannerRequestID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
        if($result->rowCount() == 1) {
            $request = $result->fetch();
            if($request['teacherPersonIDs'] != "" || $request['studentPersonIDs'] != "") {
                $people = array();
                foreach (explode(",", $request["teacherPersonIDs"]) as $teacher) {
                    $people[] = array("role" => "Teacher", "gibbonPersonID" => $teacher);
                }

                foreach (explode(",", $request["studentPersonIDs"]) as $student) {
                    $people[] = array("role" => "Student", "gibbonPersonID" => $student);
                }
                $sql1 = "INSERT INTO tripPlannerRequestPerson SET tripPlannerRequestID=:tripPlannerRequestID, gibbonPersonID=:gibbonPersonID, role=:role";
                foreach ($people as $person) {
                    $person['tripPlannerRequestID'] = $tripPlannerRequestID;
                    $result1 = $connection2->prepare($sql1);
                    $result1->execute($person);
                }

                $sql2 = "UPDATE tripPlannerRequests SET teacherPersonIDs='', studentPersonIDs='' WHERE tripPlannerRequestID=:tripPlannerRequestID";
                $result2 = $connection2->prepare($sql2);
                $result2->execute($data);
                $request["teacherPersonIDs"] = "";
                $request["studentPersonIDs"] = "";
            }
            return $request; 
        }
    } catch (PDOException $e) {
    }
    return null;
}

function getPeopleInTrip($connection2, $trips, $role=null)
{
    if(!is_array($trips) || empty($trips)) {
        return null;
    }

    try {
        $data = array();
        $sql = "SELECT tripPlannerRequestID, gibbonPersonID, role FROM tripPlannerRequestPerson WHERE (";
        foreach ($trips as $key => $trip) {
            $tData = "trip" . $key;
            $data[$tData] = $trip;
            $sql .= "tripPlannerRequestID=:" . $tData . " OR ";
        }
        $sql = substr($sql, 0, -4) . ")";
        if ($role != null) {
            $data["role"] = $role;
            $sql .= " AND role=:role";
        }
        $result = $connection2->prepare($sql);
        $result->execute($data);
        return $result;
    } catch (PDOException $e) {
    }

    return null;
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
    } else {
        return false;
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

function getPersonBlock($guid, $connection2, $gibbonPersonID, $role, $numPerRow=5, $emergency=false, $medical=false)
{
    try {
        $data = array('gibbonPersonID' => $gibbonPersonID);
        $sql = 'SELECT title, surname, preferredName, image_240, emergency1Name, emergency1Number1, emergency1Number2, emergency1Relationship, emergency2Name, emergency2Number1, emergency2Number2, emergency2Relationship FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID';
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        // echo "<div class='error'>".$e->getMessage().'</div>';
    }

    try {
        $dataFamily = array('gibbonPersonID' => $gibbonPersonID);
        $sqlFamily = 'SELECT * FROM gibbonFamily JOIN gibbonFamilyChild ON (gibbonFamily.gibbonFamilyID=gibbonFamilyChild.gibbonFamilyID) WHERE gibbonPersonID=:gibbonPersonID';
        $resultFamily = $connection2->prepare($sqlFamily);
        $resultFamily->execute($dataFamily);
    } catch (PDOException $e) {
    }

    if ($result->rowCount() == 1) {
        $row = $result->fetch();
        $width = 100.0 / $numPerRow;
        print "<td style='border: 1px solid #rgba (1,1,1,0); width:$width%; text-align: center; vertical-align: top'>";
            print "<div>";
                print getUserPhoto($guid, $row['image_240'], 75);
            print "</div>";
            print "<div><b>";
                print formatName($row['title'], $row['preferredName'], $row['surname'], $role);
            print "</b><br/></div>";
            if($emergency) {
                print "<div id='em$gibbonPersonID' style='font-size:11px'>";
                    if($resultFamily->rowCount() == 1) {
                        $rowFamily = $resultFamily->fetch();
                        try {
                            $dataMember = array('gibbonFamilyID' => $rowFamily['gibbonFamilyID']);
                            $sqlMember = 'SELECT * FROM gibbonFamilyAdult JOIN gibbonPerson ON (gibbonFamilyAdult.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE gibbonFamilyID=:gibbonFamilyID ORDER BY contactPriority, surname, preferredName';
                            $resultMember = $connection2->prepare($sqlMember);
                            $resultMember->execute($dataMember);
                        } catch (PDOException $e) {
                        }

                        while ($rowMember = $resultMember->fetch()) {
                            print "<b>" . formatName($rowMember['title'], $rowMember['preferredName'], $rowMember['surname'], 'Parent');
                            try {
                                $dataRelationship = array('gibbonPersonID1' => $rowMember['gibbonPersonID'], 'gibbonPersonID2' => $gibbonPersonID, 'gibbonFamilyID' => $rowFamily['gibbonFamilyID']);
                                $sqlRelationship = 'SELECT * FROM gibbonFamilyRelationship WHERE gibbonPersonID1=:gibbonPersonID1 AND gibbonPersonID2=:gibbonPersonID2 AND gibbonFamilyID=:gibbonFamilyID';
                                $resultRelationship = $connection2->prepare($sqlRelationship);
                                $resultRelationship->execute($dataRelationship);
                            } catch (PDOException $e) {
                            }
                            if ($resultRelationship->rowCount() == 1) {
                                $rowRelationship = $resultRelationship->fetch();
                                print " (" . $rowRelationship['relationship'] . ")";
                            }
                            print "</b><br/>";
                            for ($i = 1; $i < 5; ++$i) {
                                if ($rowMember['phone'.$i] != '') {
                                    if ($rowMember['phone'.$i.'Type'] != '') {
                                        print $rowMember['phone'.$i.'Type'].':</i> ';
                                    }
                                    if ($rowMember['phone'.$i.'CountryCode'] != '') {
                                        print '+'.$rowMember['phone'.$i.'CountryCode'].' ';
                                    }
                                    print __($guid, $rowMember['phone'.$i]).'<br/>';
                                }
                            }
                        }
                    }
                    if($row["emergency1Name"] != "") {
                            print "<b>" . $row["emergency1Name"] . " (" . $row["emergency1Relationship"] . ")</b><br/>";
                            print $row["emergency1Number1"] . "<br/>";
                            print $row["emergency1Number2"] . "<br/>";
                    }
                    if($row["emergency2Name"] != "") {
                            print "<b>" . $row["emergency2Name"] . " (" . $row["emergency2Relationship"] . ")</b><br/>";
                            print $row["emergency2Number1"] . "<br/>";
                            print $row["emergency2Number2"];
                    }
                print "</div>";
            }
        print "</td>";
    }
}

function requestNotification($guid, $connection2, $tripPlannerRequestID, $gibbonPersonID, $action)
{
    $ownerOnly = true;
    
    if ($action == "Approved") {
        $message = __($guid, 'Your trip request has been fully approved.');
    } elseif ($action == "Awaiting Final Approval") {
        $message = __($guid, 'Your trip request is awaiting final approval.');
    } elseif ($action == "Rejected") {
        $message = __($guid, 'Your trip request has been rejected.');
    } else {
        $message = __($guid, 'Someone has commented on a trip request.');
        $ownerOnly = false;
    }

    if($ownerOnly) {
        $owner = getOwner($connection2, $tripPlannerRequestID);
        if($owner != $gibbonPersonID) {
            setNotification($connection2, $guid, $owner, $message, "Trip Planner", "/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID=" . $tripPlannerRequestID);
        }
    } else {
        try {
            $data = array('tripPlannerRequestID' => $tripPlannerRequestID);
            $sql = 'SELECT DISTINCT gibbonPersonID FROM tripPlannerRequestLog WHERE tripPlannerRequestLog.tripPlannerRequestID=:tripPlannerRequestID ORDER BY timestamp';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            echo "<div class='error'>".$e->getMessage().'</div>';
        }

        while($row = $result->fetch()) {
            if($row["gibbonPersonID"] != $gibbonPersonID) {
                setNotification($connection2, $guid, $row["gibbonPersonID"], $message, "Trip Planner", "/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID=" . $tripPlannerRequestID);
            }
        }
    }
}

function notifyApprovers($guid, $connection2, $tripPlannerRequestID, $owner, $title)
{
    $approvers = getApprovers($connection2)->fetchAll();
    if (isset($approvers) && !empty($approvers) && is_array($approvers)) {
        $requestApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");
        if($requestApprovalType != null) {
            if ($requestApprovalType == "One Of" || $requestApprovalType == "Two Of") {
                foreach ($approvers as $approver) {
                    if ($approver["gibbonPersonID"] != $owner) {
                        setNotification($connection2, $guid, $approver['gibbonPersonID'], "A new trip has ben requested (" . $title .  ").", "Trip Planner", "/index.php?q=/modules/Trip Planner/trips_requestApprove.php&tripPlannerRequestID=" . $tripPlannerRequestID);   
                    }
                }
            } else {
                setNotification($connection2, $guid, $approvers[0]['gibbonPersonID'], "A new trip has ben requested (" . $title .  ").", "Trip Planner", "/index.php?q=/modules/Trip Planner/trips_requestApprove.php&tripPlannerRequestID=" . $tripPlannerRequestID);   
            }
        }
    }
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
                                print "<div id='show$i'  title='" . __($guid, 'Show/Hide') . "' style='margin-top: -1px; margin-left: 3px; padding-right: 1px; float: right; width: 23px; height: 25px; background-image: url(\"" . $_SESSION[$guid]["absoluteURL"] . "/themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/plus.png\")'></div></br>";
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

function getPastTrips($guid, $connection2, $people)
{
    if (!is_array($people) || empty($people)) {
        return null;
    }

    try {
        $date = new DateTime();
        $data = array("gibbonSchoolYearID" => $_SESSION[$guid]["gibbonSchoolYearID"], "today" => $date->format('Y-m-d'));
        $data["today"] = "2016-08-01";
        $sql = "SELECT DISTINCT tripPlannerRequests.tripPlannerRequestID, startDate, endDate, startTime, endTime FROM tripPlannerRequests JOIN tripPlannerRequestPerson ON (tripPlannerRequestPerson.tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID) WHERE status='Approved' AND date>:today AND gibbonSchoolYearID=:gibbonSchoolYearID AND (";
        foreach ($people as $key => $id) {
            $pData = "person" . $key;
            $data[$pData] = $id;
            $sql .= "tripPlannerRequestPerson.gibbonPersonID=:" . $pData . " OR ";
        }
        $sql = substr($sql, 0, -4) . ")";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }

    return $result;
}

function getPlannerOverlaps($connection2, $tripPlannerRequestID, $startDates, $endDates = array(), $startTimes = array(), $endTimes = array(), $people)
{
    if (!is_array($people) || empty($people) || !is_array($startDates) || empty($startDates) || !is_array($endDates) || !is_array($startTimes) || !is_array($endTimes)) {
        return null;
    }

    try {
        $data = array();
        if ($tripPlannerRequestID != "" && $tripPlannerRequestID != null) {
            $data["tripPlannerRequestID"] = $tripPlannerRequestID;
        }
        $sql = "SELECT DISTINCT gibbonCourse.gibbonCourseID, gibbonCourse.nameShort, gibbonCourseClass.gibbonCourseClassID, gibbonTTDayDate.date, timeStart, timeEnd" . ($tripPlannerRequestID != "" && $tripPlannerRequestID != null ? ", requiresCover" : "") . " FROM gibbonTTDayRowClass JOIN gibbonTTColumnRow ON (gibbonTTDayRowClass.gibbonTTColumnRowID = gibbonTTColumnRow.gibbonTTColumnRowID) JOIN gibbonCourseClassPerson ON (gibbonTTDayRowClass.gibbonCourseClassID = gibbonCourseClassPerson.gibbonCourseClassID) JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID = gibbonTTDayRowClass.gibbonCourseClassID) JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID = gibbonCourseClass.gibbonCourseID)" . ($tripPlannerRequestID != "" && $tripPlannerRequestID != null ? " LEFT JOIN tripPlannerRequestCover ON (tripPlannerRequestCover.gibbonCourseClassID = gibbonCourseClass.gibbonCourseClassID AND tripPlannerRequestID=:tripPlannerRequestID)" : "") . " LEFT JOIN gibbonTTDayDate ON (gibbonTTDayDate.gibbonTTDayID=gibbonTTDayRowClass.gibbonTTDayID) WHERE (";
        for ($i = 0; $i < count($startDates); $i++) {
            $sDayData = "startDate" . $i;
            $eDayData = "endDate" . $i;
            $eTimeData = "endTime" . $i;
            $sTimeData = "startTime" . $i;
            $data[$sDayData] = $startDates[$i];
            if (isset($endDates[$i])) {
                if ($endDates[$i] != null) {
                    $data[$eDayData] = $endDates[$i];
                }
            }

            if (isset($endTimes[$i]) && isset($startTimes[$i])) {
                if ($endTimes[$i] != null && $startTimes[$i] != null) {
                    $data[$eTimeData] = $endTimes[$i];
                    $data[$sTimeData] = $startTimes[$i];
                }
            }

            $sql .= "(";
            if (isset($data[$eDayData])) {
                $sql .= "gibbonTTDayDate.date >=:" . $sDayData . " AND gibbonTTDayDate.date <=:" . $eDayData;
            } else {
                $sql .= "gibbonTTDayDate.date =:" . $sDayData;
            }

            if (isset($data[$eTimeData]) && isset($data[$sTimeData])) {
                $sql .= " AND timeStart <:" . $eTimeData . " AND timeEnd >:" . $sTimeData;
            }

            $sql .= ") OR ";
        }
        $sql = substr($sql, 0, -4) . ") AND gibbonPersonID IN (";
        foreach ($people as $key => $id) {
            $pData = "people" . $key;
            $data[$pData] = $id;
            $sql .= ":" . $pData . ",";
        }
        $sql = substr($sql, 0, -1) . ")"; 
        $sql .= " ORDER BY gibbonCourse.nameShort ASC, gibbonTTDayDate.date ASC";
        //print $sql;
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        print $e;
    }

    return $result;
}

function getStudentsInClass($connection2, $classes) {

    if(!is_array($classes) || empty($classes)) {
        return null;
    }

    try {
        $data = array();
        $sql = "SELECT gibbonCourseClassID, gibbonPerson.gibbonPersonID, preferredName, surname FROM gibbonCourseClassPerson JOIN gibbonPerson ON (gibbonCourseClassPerson.gibbonPersonID = gibbonPerson.gibbonPersonID) WHERE role='Student' AND (";
        foreach ($classes as $key => $class) {
            $cData = "class" . $key;
            $data[$cData] = $class;
            $sql .= "gibbonCourseClassID=:" . $cData . " OR ";
        }
        $sql = substr($sql, 0, -4) . ")";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }   

    return $result;
}

function getTeachersOfClass($connection2, $gibbonCourseClassID) {
    try {
        $data = array("gibbonCourseClassID" => $gibbonCourseClassID);
        $sql = "SELECT gibbonCourseClassPerson.gibbonPersonID FROM gibbonCourseClassPerson WHERE gibbonCourseClassID=:gibbonCourseClassID AND role='Teacher'";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
    }   

    return $result;
}

function renderTrip($guid, $connection2, $tripPlannerRequestID, $mode) {
    if(!isset($guid) || !isset($connection2) || !isset($tripPlannerRequestID) || !isset($mode)) {
        print "<div class='error'>";
            print "Database error.";
        print "</div>";
        return;
    }

    if($tripPlannerRequestID < 0 || ($mode != "View" && $mode != "Edit" && $mode != "Approve")) {
        print "<div class='error'>";
            print "Invalid trip.";
        print "</div>";
        return;
    }

    if (($request = getTrip($connection2, $tripPlannerRequestID)) != null) {
        if ($mode == "Approve" && $request["status"] != "Requested") {
            print "<div class='error'>";
                print "This trip is already approved.";
            print "</div>";
        } else {
            $date = DateTime::createFromFormat("Y-m-d", $request["date"]);
            if($request["endDate"] != null) $endDate = DateTime::createFromFormat("Y-m-d", $request["endDate"]);
            if($request["startTime"] != null) $startTime = DateTime::createFromFormat("H:i:s", $request["startTime"]);
            if($request["endTime"] != null) $endTime = DateTime::createFromFormat("H:i:s", $request["endTime"]);


            $multiDay = isset($endDate);
            $allDay = !isset($startTime) || !isset($endTime);

            $teachers = array();
            $students = array();
            $peopleInTrip = getPeopleInTrip($connection2, array($tripPlannerRequestID), null);
            while ($people = $peopleInTrip->fetch()) {
                if ($people['role'] == "Student") {
                    $students[] = $people['gibbonPersonID'];
                } else {
                    $teachers[] = $people['gibbonPersonID'];
                }
            }

            $link = $_SESSION[$guid]['absoluteURL'].'/modules/Trip Planner/trips_request' . $mode . "Process.php";

            if ($mode != "Edit" && isOwner($connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"])) {
                echo "<div class='linkTop'>";
                    echo "<a href='".$_SESSION[$guid]['absoluteURL']."/index.php?q=/modules/Trip Planner/trips_requestEdit.php&tripPlannerRequestID=$tripPlannerRequestID'>".__($guid, 'Edit')."<img style='margin-left: 5px' title='".__($guid, 'Edit')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/config.png'/></a>";
                echo '</div>';
            } else if ($mode != "View") {
                echo "<div class='linkTop'>";
                    echo "<a href='".$_SESSION[$guid]['absoluteURL']."/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID=$tripPlannerRequestID'>".__($guid, 'View')."<img style='margin-left: 5px' title='".__($guid, 'View')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/plus.png'/></a>";
                echo '</div>';
            }

            ?>
            <form method="post" action="<?php echo $link ?>">
                <table class="smallIntBorder fullWidth" cellspacing=0>
                    <tr class="break">
                        <td colspan=2>
                            <h3>
                                Basic Information
                                <?php print "<div id='showBasic'  title='" . __($guid, 'Show/Hide') . "' style='margin-top: -5px; margin-left: 3px; padding-right: 1px; float: right; width: 23px; height: 25px; background-image: url(\"" . $_SESSION[$guid]["absoluteURL"] . "/themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/minus.png\")'></div>"; ?>
                            </h3>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $('#showBasic').unbind('click').click(function() {
                                        if ($("#basicInfo").is(":visible")) {
                                            $("#basicInfo").css("display", "none");
                                            $('#showBasic').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/plus.png' ?>')");
                                        } else {
                                            $("#basicInfo").fadeIn("fast", $("#basicInfo").css("display","table-row-group"));
                                            $('#showBasic').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/minus.png' ?>')");
                                        }
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                    <tbody id='basicInfo'>
                        <tr>
                            <td> 
                                <b><?php echo __($guid, 'Title') ?> *</b><br/>
                            </td>
                            <td class="right">
                                <input <?php print ($mode == "Edit" ? "" : "readOnly") ?> name="title" id="title" maxlength=60 value="<?php echo $request['title']; ?>" type="text" class="standardWidth">
                            </td>
                        </tr>
                        <tr>
                            <td colspan=2> 
                                <b><?php echo __($guid, 'Description') ?></b>
                                <?php 
                                if ($mode == "Edit") {
                                    print getEditor($guid, TRUE, "description", $request["description"], 5, true, true, false);
                                } else {
                                ?>
                                <?php 
                                    echo '<p>';
                                    echo $request['description'];
                                    echo '</p>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td> 
                                <b><?php echo __($guid, 'Location') ?> *</b><br/>
                            </td>
                            <td class="right">
                                <input <?php ($mode == "Edit" ? : print "readonly") ?> name="location" id="location" maxlength=60 value="<?php echo $request['location']; ?>" type="text" class="standardWidth">
                            </td>
                        </tr>
                        <tr>
                            <td> 
                                <b><?php echo __($guid, 'Status') ?> *</b><br/>
                            </td>
                            <?php
                            if($mode == "Edit") {
                                ?>
                                <td class="right">
                                    <?php
                                    echo "<select name='status' id='status' style='width:302px'>";
                                        echo "<option value='" . $request['status'] . "'>".__($guid, $request['status']).'</option>';
                                        echo "<option value='Cancelled'>".__($guid, 'Cancelled').'</option>';
                                    echo '</select>';
                                    ?>
                                    <input name="status2" id="status2" value="<?php echo $request['status'] ?>" type="hidden">
                                </td>
                                <?php
                            } else {
                                ?>
                                <td class="right">
                                    <input readonly name="status" id="status" maxlength=60 value="<?php echo $request['status']; ?>" type="text" class="standardWidth">
                                </td>
                                <?php
                            }
                            ?>
                        </tr>
                    </tbody>
                    <tr class="break">
                        <td colspan=2>
                            <h3>
                                Date & Time
                                <?php print "<div id='showDate'  title='" . __($guid, 'Show/Hide') . "' style='margin-top: -5px; margin-left: 3px; padding-right: 1px; float: right; width: 23px; height: 25px; background-image: url(\"" . $_SESSION[$guid]["absoluteURL"] . "/themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/minus.png\")'></div>"; ?>
                            </h3>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $('#showDate').unbind('click').click(function() {
                                        if ($("#dateInfo").is(":visible")) {
                                            $("#dateInfo").css("display", "none");
                                            $('#showDate').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/plus.png' ?>')");
                                        } else {
                                            $("#dateInfo").fadeIn("fast", $("#dateInfo").css("display","table-row-group"));
                                            $('#showDate').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/minus.png' ?>')");
                                        }
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                    <tbody id="dateInfo">
                        <?php
                            if ($mode == "Edit") {
                                ?>
                                <tr>
                                    <td> 
                                        <b><?php print _('Multiple Days') ?></b><br/>
                                    </td>
                                    <script type="text/javascript">
                                        function adjustDays() {
                                            var allDay = document.getElementById("multiDays");
                                            if (allDay.checked) {
                                                $("#endDateArea").slideDown("fast", $("#endDateArea").css("display","table-row"));

                                            } else {
                                                $('#endDateArea').css("display","none");
                                            }
                                        }
                                    </script>
                                    <td class="right">
                                        <input <?php $multiDay ? print "checked" : print "" ?> type="checkbox" id="multiDays" value="multiDays" onchange="adjustDays()">
                                    </td>
                                </tr>
                                <tr>
                                    <td> 
                                        <b><?php print _('Date') ?> *</b><br/>
                                        <span style="font-size: 90%"><i><?php print $_SESSION[$guid]["i18n"]["dateFormat"]  ?></i></span>
                                    </td>
                                    <td class="right">
                                        <input name="date" id="date" maxlength=10 value="<?php echo $date->format('d/m/Y'); ?>" type="text" style="width: 300px">
                                        <script type="text/javascript">
                                            var date = new LiveValidation('date');
                                            date.add(Validate.Presence);
                                            date.add(Validate.Format, {pattern: <?php if ($_SESSION[$guid]["i18n"]["dateFormatRegEx"] == "") { print "/^(0[1-9]|[12][0-9]|3[01])[- /.](0[1-9]|1[012])[- /.](19|20)\d\d$/i"; } else { print $_SESSION[$guid]["i18n"]["dateFormatRegEx"]; } ?>, failureMessage: "Use <?php if ($_SESSION[$guid]["i18n"]["dateFormat"]=="") { print "dd/mm/yyyy"; } else { print $_SESSION[$guid]["i18n"]["dateFormat"]; }?>." } ); 
                                        </script>
                                        <script type="text/javascript">
                                            $(function() {
                                                $("#date").datepicker({
                                                    onClose: function () {
                                                        this.focus();
                                                    }
                                                });
                                            });
                                        </script>
                                    </td>
                                </tr>
                                <tr id="endDateArea" style="display:<?php $multiDay ? print "table-row" : print "none"; ?>;">
                                    <td> 
                                        <b><?php print _('End Date') ?> *</b><br/>
                                        <span style="font-size: 90%"><i><?php print $_SESSION[$guid]["i18n"]["dateFormat"]  ?></i></span>
                                    </td>
                                    <td class="right">
                                        <input name="endDate" id="endDate" maxlength=10 value="<?php echo ($multiDay ? $endDate->format('d/m/Y') : "") ?>" type="text" style="width: 300px">
                                        <script type="text/javascript">
                                            var endDate = new LiveValidation('endDate');
                                            endDate.add(Validate.Format, {pattern: <?php if ($_SESSION[$guid]["i18n"]["dateFormatRegEx"] == "") { print "/^(0[1-9]|[12][0-9]|3[01])[- /.](0[1-9]|1[012])[- /.](19|20)\d\d$/i"; } else { print $_SESSION[$guid]["i18n"]["dateFormatRegEx"]; } ?>, failureMessage: "Use <?php if ($_SESSION[$guid]["i18n"]["dateFormat"]=="") { print "dd/mm/yyyy"; } else { print $_SESSION[$guid]["i18n"]["dateFormat"]; }?>." } ); 
                                        </script>
                                        <script type="text/javascript">
                                            $(function() {
                                                $("#endDate").datepicker({
                                                    onClose: function () {
                                                        this.focus();
                                                    }
                                                });
                                            });
                                        </script>
                                    </td>
                                </tr>
                                <tr>
                                    <td> 
                                        <b><?php print _('All Day') ?></b><br/>
                                    </td>
                                    <script type="text/javascript">
                                        function adjustTime() {
                                            var allDay = document.getElementById("allDay");
                                            if (allDay.checked) {
                                                $('#startTimeArea').css("display","none");
                                                $('#endTimeArea').css("display","none");
                                            } else {
                                                $("#startTimeArea").slideDown("fast", $("#startTimeArea").css("display","table-row"));
                                                $("#endTimeArea").slideDown("fast", $("#endTimeArea").css("display","table-row"));
                                            }
                                        }
                                    </script>
                                    <td class="right">
                                        <input <?php $allDay ? print "checked" : print "" ?> type="checkbox" id="allDay" name="allDay" onchange="adjustTime()">
                                    </td>
                                </tr>
                                <tr id='startTimeArea' style="display:<?php print ($allDay ? "none" : "table-row") ?>">
                                    <td> 
                                        <b><?php print _('Start Time') ?> *</b><br/>
                                        <span style="font-size: 90%"><i><?php print _('Format: hh:mm (24hr)') ?><br/></i></span>
                                    </td>
                                    <td class="right">
                                        <input name="startTime" id="startTime" maxlength=5 value="<?php echo (!$allDay ? $startTime->format('H:i') : "") ?>" type="text" style="width: 300px">
                                        <script type="text/javascript">
                                            var startTime=new LiveValidation('startTime');
                                            startTime.add( Validate.Format, {pattern: /^(0[0-9]|[1][0-9]|2[0-3])[:](0[0-9]|[1-5][0-9])/i, failureMessage: "Use hh:mm" } ); 
                                        </script>
                                    </td>
                                </tr>
                                <tr id='endTimeArea' style="display:<?php print ($allDay ? "none" : "table-row") ?>">
                                    <td> 
                                        <b><?php print _('End Time') ?> *</b><br/>
                                        <span style="font-size: 90%"><i><?php print _('Format: hh:mm (24hr)') ?><br/></i></span>
                                    </td>
                                    <td class="right">
                                        <input name="endTime" id="endTime" maxlength=5 value="<?php echo (!$allDay ? $endTime->format('H:i') : "") ?>" type="text" style="width: 300px">
                                        <script type="text/javascript">
                                            var endTime=new LiveValidation('endTime');
                                            endTime.add( Validate.Format, {pattern: /^(0[0-9]|[1][0-9]|2[0-3])[:](0[0-9]|[1-5][0-9])/i, failureMessage: "Use hh:mm" } ); 
                                        </script>
                                    </td>
                                </tr>
                                <?php
                            } else {
                        ?>
                        <tr>
                            <td> 
                                <b><?php echo __($guid, ($multiDay ? 'Start ' : '') . 'Date') ?> *</b><br/>
                            </td>
                            <td class="right">
                                <input readonly name="date" id="date" maxlength=60 value="<?php echo $date->format('d/m/Y'); ?>" type="text" class="standardWidth">
                            </td>
                        </tr>
                        <?php
                        if ($multiDay) {
                        ?>
                            <tr>
                                <td> 
                                    <b><?php echo __($guid, 'End Date') ?> *</b><br/>
                                </td>
                                <td class="right">
                                    <input readonly name="endDate" id="endDate" maxlength=60 value="<?php echo $endDate->format('d/m/Y'); ?>" type="text" class="standardWidth">
                                </td>
                            </tr>
                        <?php
                        }

                        if (!$allDay) {
                        ?>
                            <tr>
                                <td> 
                                    <b><?php echo __($guid, 'Start Time') ?> *</b><br/>
                                </td>
                                <td class="right">
                                    <input readonly name="startTime" id="startTime" maxlength=60 value="<?php echo $startTime->format('H:i'); ?>" type="text" class="standardWidth">
                                </td>
                            </tr>
                            <tr>
                                <td> 
                                    <b><?php echo __($guid, 'End Time') ?> *</b><br/>
                                </td>
                                <td class="right">
                                    <input readonly name="endTime" id="endTime" maxlength=60 value="<?php echo $endTime->format('H:i'); ?>" type="text" class="standardWidth">
                                </td>
                            </tr>
                        <?php
                        } else {
                            ?>
                            <tr>
                                <td colspan=2> 
                                    <b><?php echo __($guid, 'All Day') ?></b><br/>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                        ?>
                    </tbody>
                    <tr class="break">
                        <td colspan=2>
                        <?php
                            $riskAssessmentApproval = getSettingByScope($connection2, "Trip Planner", "riskAssessmentApproval");
                        ?>
                            <h3>
                                <?php echo __($guid, 'Risk Assessment & Communication') ?>
                                <?php print "<div id='showRisk'  title='" . __($guid, 'Show/Hide') . "' style='margin-top: -5px; margin-left: 3px; padding-right: 1px; float: right; width: 23px; height: 25px; background-image: url(\"" . $_SESSION[$guid]["absoluteURL"] . "/themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/minus.png\")'></div>"; ?>
                            </h3>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $('#showRisk').unbind('click').click(function() {
                                        if ($("#riskInfo").is(":visible")) {
                                            $("#riskInfo").css("display","none");
                                            $('#showRisk').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/plus.png' ?>')");
                                        } else {
                                            $("#riskInfo").slideDown("fast", $("#riskInfo").css("display","table-row-group"));
                                            $('#showRisk').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/minus.png' ?>')");
                                        }
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                    <tbody id='riskInfo'>
                        <tr>
                            <td colspan=2> 
                                <b><?php echo __($guid, 'Risk Assessment') ?></b>
                                <?php 
                                    if ($mode == "Edit") {
                                        print getEditor($guid, TRUE, "riskAssessment", $request["riskAssessment"], 5, true, $riskAssessmentApproval, false);
                                    } else {
                                        echo '<p>';
                                        echo $request['riskAssessment'];
                                        echo '</p>';
                                    }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan=2> 
                                <b><?php echo __($guid, 'Letter To Parents') ?></b>
                                <?php 
                                    if ($mode == "Edit") {
                                        print getEditor($guid, TRUE, "letterToParents", $request["letterToParents"], 5, true, true, false);
                                    } else {
                                        echo '<p>';
                                        echo $request['letterToParents'];
                                        echo '</p>';
                                    }
                                ?>
                            </td>
                        </tr>
                    </tbody>
                    <tr class="break">
                        <td colspan=2>
                            <h3>
                                Participants
                                <?php print "<div id='showPeople'  title='" . __($guid, 'Show/Hide') . "' style='margin-top: -5px; margin-left: 3px; padding-right: 1px; float: right; width: 23px; height: 25px; background-image: url(\"" . $_SESSION[$guid]["absoluteURL"] . "/themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/minus.png\")'></div>"; ?>
                            </h3>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $('#showPeople').unbind('click').click(function() {
                                        if ($("#peopleInfo").is(":visible")) {
                                            $("#peopleInfo").css("display","none");
                                            $('#showPeople').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/plus.png' ?>')");
                                        } else {
                                            $("#peopleInfo").slideDown("fast", $("#peopleInfo").css("display","table-row-group"));
                                            $('#showPeople').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/minus.png' ?>')");
                                        }
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                    <tbody id="peopleInfo">
                        <tr>
                            <td colspan=2>
                                <b><?php echo __($guid, 'Teachers') ?></b>
                                <table class='noIntBorder' cellspacing='0' style='width:100%;'>
                                    <tr>
                                        <?php
                                            $teacherCount = count($teachers);
                                            $teacherCount += 5 - ($teacherCount % 5);
                                            for ($i = 0; $i < $teacherCount; $i++) {
                                                if ($i % 5 == 0) {
                                                    print "</tr>";
                                                    print "<tr>";
                                                } 
                                                if (isset($teachers[$i])) {
                                                    getPersonBlock($guid, $connection2, $teachers[$i], "Staff");
                                                } else {
                                                    print "<td>";
                                                    print "</td>";
                                                }
                                            } 
                                        ?>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td colspan=2>
                                <b><?php echo __($guid, 'Students') ?></b>
                                <?php
                                    echo "<div class='linkTop'>";
                                        if(isActionAccessible($guid, $connection2, '/modules/Students/report_student_medicalSummary_print.php')) {
                                            echo "<a target='_blank' href='".$_SESSION[$guid]['absoluteURL']."/modules/Trip Planner/trips_requestExternalReportProcess.php?tripPlannerRequestID=$tripPlannerRequestID&report=medical'>".__($guid, 'Medical Info')."<img style='margin-right: 10px;margin-left: 5px' title='".__($guid, 'Medical Info')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/print.png'/></a>";
                                        }
                                        if(isActionAccessible($guid, $connection2, '/modules/Students/report_student_emergencySummary_print.php')) {
                                            echo "<a target='_blank' href='".$_SESSION[$guid]['absoluteURL']."/modules/Trip Planner/trips_requestExternalReportProcess.php?tripPlannerRequestID=$tripPlannerRequestID&report=emergency'>".__($guid, 'Emergency Info')."<img style='margin-right: 10px;margin-left: 5px' title='".__($guid, 'Emergency Info')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/print.png'/></a>";
                                        }
                                        echo "<a target='_blank' href='".$_SESSION[$guid]['absoluteURL']."/report.php?q=/modules/Trip Planner/trips_reportTripPeople.php&tripPlannerRequestID=$tripPlannerRequestID'>".__($guid, 'Student List')."<img style='margin-left: 5px' title='".__($guid, 'Student List')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/print.png'/></a>";
                                    echo '</div>';
                                ?>
                                <table class='noIntBorder' cellspacing='0' style='width:100%;'>
                                    <tr>
                                        <?php
                                            $numPerRow = 5;
                                            $studentCount = count($students);
                                            $studentCount += $numPerRow - ($studentCount % $numPerRow);
                                            for ($i = 0; $i < $studentCount; $i++) {
                                                if ($i % $numPerRow == 0) {
                                                    print "</tr>";
                                                    print "<tr>";
                                                } 
                                                if (isset($students[$i])) {
                                                    getPersonBlock($guid, $connection2, $students[$i], "Student", $numPerRow);
                                                } else {
                                                    print "<td>";
                                                    print "</td>";
                                                }
                                            } 
                                        ?>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                    <tr class="break">
                        <td colspan=2>
                            <h3>
                                Cost Breakdown
                                <?php print "<div id='showCost'  title='" . __($guid, 'Show/Hide') . "' style='margin-top: -5px; margin-left: 3px; padding-right: 1px; float: right; width: 23px; height: 25px; background-image: url(\"" . $_SESSION[$guid]["absoluteURL"] . "/themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/minus.png\")'></div>"; ?>
                            </h3>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $('#showCost').unbind('click').click(function() {
                                        if ($("#costInfo").is(":visible")) {
                                            $("#costInfo").css("display","none");
                                            $('#showCost').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/plus.png' ?>')");
                                        } else {
                                            $("#costInfo").slideDown("fast", $("#costInfo").css("display","table-row-group"));
                                            $('#showCost').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/minus.png' ?>')");
                                        }
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                    <tbody id="costInfo">
                        <tr>
                            <td colspan=2>
                                <table cellspacing='0' style='width: 100%'>
                                    <tr class='head'>
                                        <th style='text-align: left; padding-left: 10px'>
                                            <?php print __($guid, 'Name'); ?>
                                        </th>
                                        <th style='text-align: left'>
                                            <?php print __($guid, 'Description'); ?>
                                        </th>
                                        <th style='text-align: left'>
                                            <?php 
                                                print __($guid, 'Cost') . "<br/>"; 
                                                if ($_SESSION[$guid]['currency'] != '') {
                                                    print "<span style='font-style: italic; font-size: 85%'>".$_SESSION[$guid]['currency'].'</span>';
                                                }
                                            ?>
                                        </th>
                                    </tr>
                                    <?php
                                        try {
                                            $dataCosts = array("tripPlannerRequestID" => $tripPlannerRequestID);
                                            $sqlCosts = 'SELECT tripPlannerCostBreakdownID, title, description, cost FROM tripPlannerCostBreakdown WHERE tripPlannerRequestID=:tripPlannerRequestID ORDER BY tripPlannerCostBreakdownID';
                                            $resultCosts = $connection2->prepare($sqlCosts);
                                            $resultCosts->execute($dataCosts);
                                        } catch (PDOException $e) {
                                            print "<div class='error'>".$e->getMessage().'</div>';
                                        }
                                        $totalCost = 0;
                                        $count = 0;
                                        while ($rowCosts = $resultCosts->fetch()) {
                                            $totalCost += $rowCosts['cost'];
                                            $rowNum = 'odd';
                                            if ($count % 2 == 0) {
                                                $rowNum = 'even';
                                            }

                                            print "<tr style='height: 25px' class=$rowNum>";
                                                print "<td style='padding-left: 10px'>";
                                                    print $rowCosts['title'];
                                                print "</td>";
                                                print "<td>";
                                                    print $rowCosts['description'];
                                                print "</td>";
                                                print "<td>";
                                                    $cost = "";
                                                    if (substr($_SESSION[$guid]['currency'], 4) != '') {
                                                        $cost = substr($_SESSION[$guid]['currency'], 4).' ';
                                                    }
                                                    $cost .= number_format($rowCosts['cost'], 2, '.', ',');
                                                    print $cost;
                                                print "</td>";
                                            print "</tr>";
                                            $count++;
                                        }
                                    ?>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td> 
                                <b><?php echo __($guid, 'Total Cost') ?> *</b><br/>
                            </td>
                            <td class="right">
                                <input readonly name="totalCost" id="totalCost" maxlength=60 value="<?php echo $_SESSION[$guid]['currency'] . ' ' . $totalCost; ?>" type="text" class="standardWidth">
                            </td>
                        </tr>
                    </tbody>
                    <tr class="break">
                        <td colspan=2>
                            <h3>
                                Timetable Overlaps
                                <?php print "<div id='showPlanner'  title='" . __($guid, 'Show/Hide') . "' style='margin-top: -5px; margin-left: 3px; padding-right: 1px; float: right; width: 23px; height: 25px; background-image: url(\"" . $_SESSION[$guid]["absoluteURL"] . "/themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/minus.png\")'></div>"; ?>
                            </h3>
                            <script type="text/javascript">
                                $(document).ready(function(){
                                    $('#showPlanner').unbind('click').click(function() {
                                        if ($("#plannerInfo").is(":visible")) {
                                            $("#plannerInfo").css("display","none");
                                            $('#showPlanner').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/plus.png' ?>')");
                                        } else {
                                            $("#plannerInfo").slideDown("fast", $("#plannerInfo").css("display","table-row-group"));
                                            $('#showPlanner').css("background-image", "url('<?php print $_SESSION[$guid]['absoluteURL'].'/themes/'.$_SESSION[$guid]['gibbonThemeName'].'/img/minus.png' ?>')");
                                        }
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                    <tbody id="plannerInfo">
                        <tr>
                            <td colspan=2>
                                <table cellspacing='0' style='width: 100%'>
                                    <tr class='head'>
                                        <th style='text-align: left; padding-left: 10px; width:10%'>
                                            <?php print __($guid, 'Class'); ?>
                                        </th>
                                        <th style='text-align: left'>
                                            <?php print __($guid, 'Students Involved'); ?>
                                        </th>
                                        <th style='text-align: left; width: 10%'>
                                            <?php print __($guid, 'Require Covers?'); ?>
                                        </th>
                                        <th style='text-align: left; width:10%'>
                                            <?php print __($guid, 'Actions'); ?>
                                        </th>
                                    </tr>
                                    <?php
                                        $missedClasses = array();
                                        $pastTrips = getPastTrips($guid, $connection2, $students);
                                        if ($pastTrips != null) {
                                            if($pastTrips->rowCount() > 0) {
                                                $trips = array();
                                                $startDates = array();
                                                $endDates = array();
                                                $startTimes = array();
                                                $endTimes = array();
                                                $tripStudents = array();

                                                while ($row = $pastTrips->fetch()) {
                                                    $trips[] = $row['tripPlannerRequestID'];
                                                    $startDates[] = $row['date'];
                                                    $endDates[] = $row['endDate'];
                                                    $startTimes[] = $row['startTime'];
                                                    $endTimes[] = $row['endTime'];
                                                }

                                                $classesMissed = getPlannerOverlaps($connection2, null, $startDates, $endDates, $startTimes, $endTimes, $students);
                                                if ($classesMissed != null) {
                                                    if($classesMissed->rowCount() > 0) {
                                                        $classes = array();
                                                        $courses = array();

                                                        while ($row = $classesMissed->fetch()) {
                                                            for ($i = 0; $i < count($startDates); $i++) {
                                                                $withinDate = $endDates[$i] == null ? $withinDate = ($row["date"] == $startDates[$i]) : $withinDate = ($row['date'] >= $startDates[$i] && $endDates[$i] <= $row['date']);
                                                                if ($withinDate) {
                                                                    if($row['timeStart'] < $endTimes[$i] && $row['timeEnd'] > $startTimes[$i]) {
                                                                        $classes[] = array("arrayIndex" => $i, "gibbonCourseID" => $row['gibbonCourseID'], "nameShort" => $row['nameShort'], "gibbonCourseClassID" => $row['gibbonCourseClassID']);
                                                                        $courses[] = $row['gibbonCourseClassID'];
                                                                    }
                                                                }
                                                            }
                                                        }

                                                        if(!empty($courses)) {
                                                            $peopleInTrips = getPeopleInTrip($connection2, $trips, "Student");

                                                            while ($row = $peopleInTrips->fetch()) {
                                                                $arrayIndex = array_search($row['tripPlannerRequestID'], $trips);
                                                                if ($arrayIndex !== null) {
                                                                    if (isset($tripStudents[$arrayIndex])) {
                                                                        $tempArray = $tripStudents[$arrayIndex];
                                                                        array_push($tempArray, $row['gibbonPersonID']);
                                                                        $tripStudents[$arrayIndex] = $tempArray;
                                                                    } else {
                                                                        $tempArray = array($row['gibbonPersonID']);
                                                                        $tripStudents[$arrayIndex] = $tempArray;
                                                                    }
                                                                }
                                                            }

                                                            $peopleInClasses = getStudentsInClass($connection2, $courses);

                                                            while ($row = $peopleInClasses->fetch()) {
                                                                $keys = array_keys(array_column($classes, 'gibbonCourseClassID'), $row['gibbonCourseClassID']);
                                                                foreach ($keys as $key) {
                                                                    if ($key !== null) {
                                                                        if (isset($classes[$key]["students"])) {
                                                                            $tempArray = $classes[$key]["students"];
                                                                            array_push($tempArray, $row['gibbonPersonID']);
                                                                            $classes[$key]["students"] = $tempArray;
                                                                        } else {
                                                                            $tempArray = array($row['gibbonPersonID']);
                                                                            $classes[$key]["students"] = $tempArray;
                                                                        }
                                                                    }
                                                                }
                                                            }

                                                            foreach ($classes as $key => $class) {
                                                                $cStudents = $class["students"];
                                                                $tStudents = $tripStudents[$class["arrayIndex"]];
                                                                $gibbonCourseID = $class["gibbonCourseID"];
                                                                foreach ($cStudents as $cStudent) {
                                                                    if(in_array($cStudent, $tStudents)) {
                                                                        if(!isset($missedClasses[$cStudent])) {
                                                                            $missedClasses[$cStudent] = array();
                                                                        }

                                                                        if(isset($missedClasses[$cStudent][$gibbonCourseID])) {
                                                                            $missedClasses[$cStudent][$gibbonCourseID] = ++$missedClasses[$cStudent][$gibbonCourseID];   
                                                                        } else {
                                                                            $missedClasses[$cStudent][$gibbonCourseID] = 1;   
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        try {
                                            $sqlSetting = "SELECT value FROM gibbonSetting WHERE scope='Trip Planner' AND name='missedClassWarningThreshold'";
                                            $resultSetting = $connection2->prepare($sqlSetting);
                                            $resultSetting->execute();
                                        } catch (PDOException $e) { 
                                        }

                                        $missedClassWarningThreshold = 0;
                                        if($resultSetting->rowCount() == 1) {
                                            $missedClassWarningThreshold = $resultSetting->fetch()['value'];
                                        }

                                        $overlapStartDate = array($request["date"]);
                                        $overlapEndDate = array();
                                        if ($multiDay) {
                                            $overlapEndDate[] = $request["endDate"];
                                        }
                                        $overlapStartTime = array();
                                        $overlapEndTime = array();
                                        if (!$allDay) {
                                            $overlapStartTime[] = $request["startTime"];
                                            $overlapEndTime[] = $request["endTime"];
                                        }

                                        $overlaps = getPlannerOverlaps($connection2, $tripPlannerRequestID, $overlapStartDate, $overlapEndDate, $overlapStartTime, $overlapEndTime, array_merge($students, $teachers));
                                        if ($overlaps != null) {
                                            while ($row = $overlaps->fetch()) {
                                                $classStudents = getStudentsInClass($connection2, array($row["gibbonCourseClassID"]));
                                                $classTeachers = getTeachersOfClass($connection2, $row["gibbonCourseClassID"]);
                                                $allStudentOnTrip = true;
                                                print "<tr>";
                                                    print "<td>";
                                                        print $row["nameShort"];
                                                        print "<br/>";
                                                        print "<span style='font-size: 90%'>";
                                                            print dateConvertBack($guid, $row["date"]);
                                                        print "</span>";
                                                    print "</td>";
                                                    print "<td>";
                                                        $studentsInvolved = "";
                                                        while ($student = $classStudents->fetch()) {
                                                            if (in_array($student["gibbonPersonID"], $students)) {
                                                                $warning = false;
                                                                if ($missedClassWarningThreshold > 0 && !empty($missedClasses)) {
                                                                    if (isset($missedClasses[$student["gibbonPersonID"]])) {
                                                                        if (isset($missedClasses[$student["gibbonPersonID"]][$row['gibbonCourseID']])) {
                                                                            $warning = $missedClassWarningThreshold <= $missedClasses[$student["gibbonPersonID"]][$row['gibbonCourseID']];
                                                                        }
                                                                    }
                                                                }
                                                                if ($warning) {
                                                                    //$studentsInvolved .= "<a class='thickbox' href='".$_SESSION[$guid]['absoluteURL']."/fullscreen.php?q=/modules/Trip Planner/trips_requestStudentInformation.php&tripPlannerRequestID=$tripPlannerRequestID&gibbonCourseID=" . $row["gibbonCourseID"] . "&gibbonPersonID=" . $student["gibbonPersonID"] . "&width=1000&height=550'>";
                                                                    $studentsInvolved .= "<b style='color:#F50000'>";
                                                                }
                                                                $studentsInvolved .= $student["preferredName"] . " " . $student["surname"];
                                                                if ($warning) {
                                                                    $studentsInvolved .= "</b>";
                                                                    //$studentsInvolved .= "</a>";
                                                                }
                                                                $studentsInvolved .= ", ";
                                                            } else {
                                                                $allStudentOnTrip = false;
                                                            }
                                                        }
                                                        print substr($studentsInvolved, 0, -2);
                                                    print "</td>";
                                                    print "<td>";
                                                        $systemMessage = "";
                                                        $requiresCover = $row["requiresCover"];
                                                        if ($requiresCover == null) {
                                                            $allTeachersOnTrip = true;
                                                            while ($teacher = $classTeachers->fetch()) {
                                                                if (!in_array($teacher['gibbonPersonID'], $teachers)) {
                                                                    $allTeachersOnTrip = false;
                                                                    break;
                                                                }
                                                            }
                                                            $requiresCover = !$allStudentOnTrip && $allTeachersOnTrip;
                                                            $systemMessage = " (This is an Automated Suggestion)";
                                                        }

                                                        print ($requiresCover ? "Yes" : "No") . $systemMessage;
                                                    print "</td>";
                                                    print "<td>";
                                                        if ($mode == "Edit") {
                                                            echo "<a class='thickbox' href='".$_SESSION[$guid]['absoluteURL']."/fullscreen.php?q=/modules/Trip Planner/trips_requestCoverStatus.php&tripPlannerRequestID=$tripPlannerRequestID&gibbonCourseClassID=" . $row["gibbonCourseClassID"] . "&requiresCover=$requiresCover&width=1000&height=550'><img title='".__($guid, 'Change Cover Status')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/config.png'/></a> ";
                                                        }
                                                        
                                                        if ($requiresCover) {
                                                            echo "<a class='thickbox' href='".$_SESSION[$guid]['absoluteURL']."/fullscreen.php?q=/modules/Trip Planner/trips_requestCoverTeachers.php&tripPlannerRequestID=$tripPlannerRequestID&date=" . $row["date"] . "&timeStart=" . $row["timeStart"] . "&timeEnd=" . $row["timeEnd"] . "&width=1000&height=550'><img title='".__($guid, 'View Possible Cover Teachers')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/plus.png'/></a> ";
                                                        }
                                                        //print "<a><img title='" . _('View') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/plus.png'/></a> ";
                                                    print "</td>";
                                                print "</tr>";
                                            }
                                        }
                                    ?>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                    <tr class="break">
                        <td colspan=2>
                            <h3>
                                Log
                            </h3>
                        </td>
                    </tr>
                    <tr>
                        <td colspan=2>
                            <?php
                                print getRequestLog($guid, $connection2, $tripPlannerRequestID);
                            ?>
                        </td>
                    </tr>
                    <?php
                    if ($mode == "Approve") {
                        ?>
                        <tr class="break">
                            <td>
                                <h3>
                                    Action
                                </h3>
                            </td>
                        </tr>
                        <?php
                        if (needsApproval($connection2, $tripPlannerRequestID, $_SESSION[$guid]['gibbonPersonID']) != 0) {
                            ?>
                            <tr>
                                <td colspan=2> 
                                    <div class='error'><?php echo __($guid, 'Your approval is not currently required: it is possible someone beat you to it, or you have already approved it.') ?></div>
                                </td>
                            </tr>
                            <?php
                        } else {
                            ?>
                            <tr>
                                <td style='width: 275px'> 
                                    <b><?php echo __($guid, 'Approval') ?> *</b><br/>
                                </td>
                                <td class="right">
                                    <?php
                                    echo "<select name='approval' id='approval' style='width:302px'>";
                                        echo "<option value='Please select...'>".__($guid, 'Please select...').'</option>';
                                        echo "<option value='Approval - Partial'>".__($guid, 'Approve').'</option>';
                                        echo "<option value='Rejection'>".__($guid, 'Reject').'</option>';
                                        echo "<option value='Comment'>".__($guid, 'Comment').'</option>';
                                    echo '</select>';
                                    ?>
                                    <script type="text/javascript">
                                        var approval=new LiveValidation('approval');
                                        approval.add(Validate.Exclusion, { within: ['Please select...'], failureMessage: "<?php echo __($guid, 'Select something!') ?>"});
                                    </script>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                    <tr>
                        <td colspan=2> 
                            <b><?php echo __($guid, 'Comment') ?></b><br/>
                            <textarea name="comment" id="comment" rows=8 style="resize:vertical; width: 100%"></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="emphasis small">* <?php echo __($guid, 'denotes a required field'); ?></span>
                        </td>
                        <td class="right">
                            <input name="tripPlannerRequestID" id="tripPlannerRequestID" value="<?php echo $tripPlannerRequestID ?>" type="hidden">
                            <input type="submit" value="<?php echo __($guid, 'Submit'); ?>">
                        </td>
                    </tr>
                </table>
            </form>
            <?php
        }
    } else {
        print "<div class='error'>";
            print "Database error.";
        print "</div>";
    }
}

function sort2DArray($array, $key, $sortStyle=SORT_ASC, $sortType=SORT_STRING) {
    $column = array_column($array, $key);
    array_multisort($column, $sortStyle, $sortType, $array);
    return $array;
}
?>
