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
    $URL .= "trips_manage&return=error0";
    header("Location: {$URL}");
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
            }

            if (isset($_POST["status2"])) {
                $status2 = $_POST["status2"];
            } else {
                $URL .= "&return=error1";
                header("Location: {$URL}");
            }

            if (isset($_POST["comment"])) {
                $comment = $_POST["comment"];
            }

            if ($status != $status2) {
                $action = "Cancellation";
                if (!logEvent($connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"], $action, $comment)) {
                    $URL .= "&return=error2";
                    header("Location: {$URL}");
                }

                try {
                    $data = array("tripPlannerRequestID" => $tripPlannerRequestID);
                    $sql = "UPDATE tripPlannerRequests SET status='Cancelled' WHERE tripPlannerRequestID=:tripPlannerRequestID";
                    $result = $connection2->prepare($sql);
                    $result->execute($data);
                } catch (PDOException $e) {
                    $URL .= "&return=error2";
                    header("Location: {$URL}");
                }
            }

            $URL .= "&return=success0";
            header("Location: {$URL}");
        } else {
            $URL .= "trips_manage&return=error0";
            header("Location: {$URL}");
        }
    } else {
        $URL .= "trips_manage&return=error1";
        header("Location: {$URL}");
    }
}   
?>
