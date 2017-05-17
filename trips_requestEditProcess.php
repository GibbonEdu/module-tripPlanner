<?php

@session_start();

//Module includes
include "../../functions.php";
include "../../config.php";

include "./moduleFunctions.php";

date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL = $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Trip Planner/";

$pdo = new Gibbon\sqlConnection();
$connection2 = $pdo->getConnection();

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php')) {
    //Acess denied
    $URL .= "trips_manage.php&return=error0";
    header("Location: {$URL}");
    exit();
} else {

    if (isset($_POST["tripPlannerRequestID"])) {
        $tripPlannerRequestID = $_POST["tripPlannerRequestID"];
        if (isOwner($connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"])) {
            $URL .= "trips_requestEdit.php&tripPlannerRequestID=" . $tripPlannerRequestID;
            
            if (isset($_POST["status"])) {
                $status = $_POST["status"];
            } else {
                $URL .= "&return=error1";
                header("Location: {$URL}");
                exit();
            }

            if (isset($_POST["status2"])) {
                $status2 = $_POST["status2"];
            } else {
                $URL .= "&return=error1";
                header("Location: {$URL}");
                exit();
            }

            $riskAssessmentApproval = getSettingByScope($connection2, "Trip Planner", "riskAssessmentApproval");

            $data = array("riskAssessment" => $riskAssessmentApproval, "letterToParents"=> $riskAssessmentApproval, "title" => true, "description" => true, "location" => true, "date" => true, "endDate" => false, "startTime" => false, "endTime" => false);

            foreach($data as $datum => $required) {
                if(isset($_POST[$datum])) {
                    if (($_POST[$datum] == "" || $_POST[$datum] == null)) {
                        if ($required) {
                            $URL .= "&return=error1";
                            header("Location: {$URL}");
                            exit();
                        } else {
                            ${$datum} = null;
                            continue;
                        }
                    }
                    if ($datum == "date" || $datum == "endDate") {
                        $datee = DateTime::createFromFormat("d/m/Y", $_POST[$datum]);
                        ${$datum} = $datee->format("Y-m-d H:i:s");
                    } else {
                        ${$datum} = $_POST[$datum];
                    }
                } elseif ($required) {
                    $URL .= "&return=error1";
                    header("Location: {$URL}");
                    exit();
                } else {
                    ${$datum} = null;
                }
            }

            if (isset($_POST["allDay"])) {
                if ($_POST["allDay"] == "on") {
                    $startTime = null;
                    $endTime = null;
                }
            }

            if (isset($_POST["comment"])) {
                $comment = $_POST["comment"];
            }

            if ($status != $status2) {
                $action = "Cancellation";
                if (!logEvent($connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"], $action, $comment)) {
                    $URL .= "&return=error2";
                    header("Location: {$URL}");
                    exit();
                }

                try {
                    $data = array("tripPlannerRequestID" => $tripPlannerRequestID);
                    $sql = "UPDATE tripPlannerRequests SET status='Cancelled' WHERE tripPlannerRequestID=:tripPlannerRequestID";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= "&return=error2";
                    header("Location: {$URL}");
                    exit();
                }
            } else {
                if (!logEvent($connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"], "Edit", $comment)) {
                    $URL .= "&return=error2";
                    header("Location: {$URL}");
                    exit();
                }

                try {
                    $data = array("tripPlannerRequestID" => $tripPlannerRequestID, "riskAssessment" => $riskAssessment, "letterToParents" => $letterToParents, "title" => $title, "description" => $description, "location" => $location, "date" => $date, "endDate" => $endDate, "startTime" => $startTime, "endTime" => $endTime);
                    $sql = "UPDATE tripPlannerRequests SET riskAssessment=:riskAssessment, letterToParents=:letterToParents, title=:title, description=:description, location=:location, date=:date, endDate=:endDate, startTime=:startTime, endTime=:endTime WHERE tripPlannerRequestID=:tripPlannerRequestID";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= "&return=error2";
                    header("Location: {$URL}");
                    exit();
                }
            }

            $URL .= "&return=success0";
            header("Location: {$URL}");
            exit();
        } else {
            $URL .= "trips_manage.php&return=error0";
            header("Location: {$URL}");
            exit();
        }
    } else {
        $URL .= "trips_manage.php&return=error1";
        header("Location: {$URL}");
        exit();
    }
}   
?>
