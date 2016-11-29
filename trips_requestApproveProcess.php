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

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manage.php') || !isApprover($connection2, $_SESSION[$guid]["gibbonPersonID"])) {
    //Acess denied
    $URL .= "trips_manage.php&return=error0";
    header("Location: {$URL}");
    exit();
} else {
    if (isset($_POST["tripPlannerRequestID"])) {
        $tripPlannerRequestID = $_POST["tripPlannerRequestID"];
        if (needsApproval($connection2, $tripPlannerRequestID, $_SESSION[$guid]['gibbonPersonID'])) {
            $URL .= "trips_requestApprove.php&tripPlannerRequestID=" . $tripPlannerRequestID;

            if (isset($_POST["approval"])) {
                $approval = $_POST["approval"];
            } else {
                $URL .= "&return=error1";
                header("Location: {$URL}");
                exit();
            }

            if (isset($_POST["comment"])) {
                $comment = $_POST["comment"];
            } elseif ($approval == "Comment") {
                $URL .= "&return=error1";
                header("Location: {$URL}");
                exit();
            }

            if ($approval == "Approval - Partial") {
                $done = false;
                $requestApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");
                if ($requestApprovalType == "One Of") {
                    $done = true;
                } elseif ($requestApprovalType == "Two Of") {
                    $done = (getEvents($connection2, $tripPlannerRequestID, array("Approval - Partial")) == 1);
                } elseif ($requestApprovalType == "Chain Of All") {
                    try {
                        $data = array("gibbonPersonID" => $_SESSION[$guid]["gibbonPersonID"]);
                        $sql = "SELECT * FROM `tripPlannerApprovers` WHERE sequenceNumber > (SELECT sequenceNumber FROM `tripPlannerApprovers` WHERE gibbonPersonID=:gibbonPersonID)";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= "&return=error2";
                        header("Location: {$URL}");
                        exit();
                    }

                    $done = $result->rowCount() == 0;
                }

                if ($done) {
                    $approval = "Approval - Final";
                    try {
                        $data = array("tripPlannerRequestID" => $tripPlannerRequestID);
                        $sql = "UPDATE tripPlannerRequests SET status='Approved' WHERE tripPlannerRequestID=:tripPlannerRequestID";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= "&return=error2";
                        header("Location: {$URL}");
                        exit();
                    }

                    requestNotification($guid, $connection2, $tripPlannerRequestID, "Approved");
                } elseif ($requestApprovalType == "Chain Of All") {
                    try {
                        $data = array("gibbonPersonID" => $_SESSION[$guid]["gibbonPersonID"]);
                        $sql = "SELECT gibbonPersonID FROM `tripPlannerApprovers` WHERE sequenceNumber = (SELECT sequenceNumber FROM `tripPlannerApprovers` WHERE gibbonPersonID=:gibbonPersonID)+1";
                        $result = $connection2->prepare($sql);
                        $result->execute($data);
                    } catch (PDOException $e) {
                        $URL .= "&return=error2";
                        header("Location: {$URL}");
                        exit();
                    }

                    $message = __($guid, 'A trip request is awaiting your approval.');
                    setNotification($connection2, $guid, $result->fetch()["gibbonPersonID"], $message, 'Trip Planner', "/index.php?q=/modules/Trip Planner/trips_requestApprove.php&tripPlannerRequestID=$tripPlannerRequestID");
                }

                if (!logEvent($connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"], $approval, $comment)) {
                    $URL .= "&return=error2";
                    header("Location: {$URL}");
                    exit();
                }
            } elseif ($approval == "Rejection") {
                try {
                    $data = array("tripPlannerRequestID" => $tripPlannerRequestID);
                    $sql = "UPDATE tripPlannerRequests SET status='Rejected' WHERE tripPlannerRequestID=:tripPlannerRequestID";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= "&return=error2";
                    header("Location: {$URL}");
                    exit();
                }
                requestNotification($guid, $connection2, $tripPlannerRequestID, "Rejected");
            } elseif ($approval == "Comment") {
                if (!logEvent($connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"], "Comment", $comment)) {
                    $URL .= "&return=error2";
                    header("Location: {$URL}");
                    exit();
                }
                requestNotification($guid, $connection2, $tripPlannerRequestID, "Comment");
            }

            $URL = $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Trip Planner/trips_manage.php&return=success0";
            header("Location: {$URL}");
            exit();
        } else {
            $URL .= "trips_requestView.php&tripPlannerRequestID=" . $tripPlannerRequestID . "&return=error0";
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
