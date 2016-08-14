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
    $URL .= "&return=error0";
    header("Location: {$URL}");
} else {
    if (isset($_POST["tripPlannerRequestID"])) {
        $tripPlannerRequestID = $_POST["tripPlannerRequestID"];
    } else {
        $URL .= "trips_manage.php&return=error1";
        header("Location: {$URL}");
        exit();
    }

    $gibbonPersonID = $_SESSION[$guid]["gibbonPersonID"];
    $departments = getHOD($connection2, $gibbonPersonID);
    $departments2 = getDepartments($connection2, getOwner($connection2, $tripPlannerRequestID));
    $isHOD = false;

    foreach ($departments as $department) {
        if (in_array($department["gibbonDepartmentID"], $departments2)) {
            $isHOD = true;
            break;
        }
    }

    if (isApprover($connection2, $gibbonPersonID) || isOwner($connection2, $tripPlannerRequestID, $gibbonPersonID) || isInvolved($connection2, $tripPlannerRequestID, $gibbonPersonID) || $isHOD) {
        $URL .= "trips_requestView.php&tripPlannerRequestID=" . $tripPlannerRequestID;
        if (isset($_POST["comment"])) {
            $comment = $_POST["comment"];
            if ($comment == "" || $comment == null) {
                $URL .= "&return=error1";
                header("Location: {$URL}");
            }
        } else {
            $URL .= "&return=error1";
            header("Location: {$URL}");
        }

        if (!logEvent($connection2, $tripPlannerRequestID, $gibbonPersonID, "Comment", $comment)) {
            $URL .= "&return=error2";
            header("Location: {$URL}");
        }
        requestNotification($guid, $connection2, $tripPlannerRequestID, "Comment");

        $URL .= "&return=success0";
        header("Location: {$URL}");
    } else {
        $URL .= "trips_manage.php&return=error0";
        header("Location: {$URL}");
    }
}   
?>
