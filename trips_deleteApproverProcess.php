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

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_deleteApproverProcess.php')) {
    //Acess denied
    $URL .= "trips_manageApprovers.php&return=error0";
    header("Location: {$URL}");
} else {
    if (isset($_GET["tripPlannerApproverID"])) {
        if ($_GET["tripPlannerApproverID"] != null && $_GET["tripPlannerApproverID"] != "") {
            $tripPlannerApproverID = $_GET["tripPlannerApproverID"];
        }
    } else {
        $URL .= "trips_manageApprovers.php&return=error1";
        header("Location: {$URL}");
    }
        
    if (!approverExists($connection2, $tripPlannerApproverID)) {
        //Fail 4
        $URL .= "trips_manageApprovers.php&return=error1";
        header("Location: {$URL}");
    } else {  
        try {
            $data=array("tripPlannerApproverID"=> $tripPlannerApproverID);
            $sql="DELETE FROM tripPlannerApprovers WHERE tripPlannerApproverID=:tripPlannerApproverID";
            $result=$connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= "trips_manageApprovers.php&return=error2";
            header("Location: {$URL}");
            exit();
        }

        $URL .= "trips_manageApprovers.php&return=success0";
        header("Location: {$URL}");
    }
}   
?>
