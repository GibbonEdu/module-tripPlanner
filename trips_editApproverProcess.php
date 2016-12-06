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

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_editApprover.php')) {
    //Acess denied
    $URL .= "trips_manageApprovers.php&return=error0";
    header("Location: {$URL}");
} else {
    $tripPlannerApproverID = null;

    if (isset($_GET["tripPlannerApproverID"])) {
        if ($_GET["tripPlannerApproverID"] != null && $_GET["tripPlannerApproverID"] != "") {
            $tripPlannerApproverID = $_GET["tripPlannerApproverID"];
        }
    } 

    if ($tripPlannerApproverID == null) {
        $URL .= "trips_manageApprovers.php&return=error1";
        header("Location: {$URL}");
    }

    $gibbonPersonID = null;
    if (isset($_POST["gibbonPersonID"])) {
        if ($_POST["gibbonPersonID"] != null && $_POST["gibbonPersonID"] != "") {
            $gibbonPersonID = $_POST["gibbonPersonID"];
        }
    } 

    if ($gibbonPersonID == null) {
        $URL .= "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=error1";
        header("Location: {$URL}");
    }

    $pdo = new Gibbon\sqlConnection();
    $connection2 = $pdo->getConnection();

    $expenseApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType");
    if ($expenseApprovalType == "Chain Of All") {
        $sequenceNumber = null;
        if (isset($_POST["sequenceNumber"])) {
            if ($_POST["sequenceNumber"] != null && $_POST["sequenceNumber"] != "") {
                $sequenceNumber = abs($_POST["sequenceNumber"]);
            }
        } 

        if ($sequenceNumber == null) {
            $URL .= "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=error1";
            header("Location: {$URL}");
        }
    } else {
        $sequenceNumber = 0;
    }

    $finalApprover = 0;
    $riskAssessmentApproval = getSettingByScope($connection2, "Trip Planner", "riskAssessmentApproval");
    if ($riskAssessmentApproval) {
        if (isset($_POST["finalApprover"])) {
            if($_POST["finalApprover"] != null && $_POST["finalApprover"] != "") {
                $finalApprover = 1;
            }
        }
    }

    try {
        if ($expenseApprovalType=="Chain Of All") {
            $approver = getApprover($connection2, $tripPlannerApproverID);
            if ($approver['gibbonPersonID'] == $gibbonPersonID && $approver['sequenceNumber'] != $sequenceNumber) {
                $data = array("sequenceNumber" => $sequenceNumber); 
                $sql = "SELECT * FROM tripPlannerApprovers WHERE sequenceNumber=:sequenceNumber";
            } else if ($approver['gibbonPersonID'] != $gibbonPersonID && $approver['sequenceNumber'] == $sequenceNumber) {
                $data = array("gibbonPersonID" => $gibbonPersonID); 
                $sql = "SELECT * FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID";
            } else {
                $data = array("gibbonPersonID" => $gibbonPersonID, "sequenceNumber" => $sequenceNumber); 
                $sql = "SELECT * FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID OR sequenceNumber=:sequenceNumber";
            }
        } else {
            $data = array("gibbonPersonID" => $gibbonPersonID); 
            $sql = "SELECT * FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID";
        }
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) { 
        //Fail 2
        $URL .= "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=error2";
        header("Location: {$URL}");
        exit();
    }
        
    if ($result->rowCount() > 0 && (approverExists($connection2, $tripPlannerApproverID && !$riskAssessmentApproval))) {
        //Fail 4
        $URL .= "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=error1";
        header("Location: {$URL}");
    } else {  
        try {
            $data = array("gibbonPersonID" => $gibbonPersonID, "sequenceNumber" => $sequenceNumber, "gibbonPersonIDUpdate" => $_SESSION[$guid]["gibbonPersonID"], "timestampUpdate" => date('Y-m-d H:i:s', time()), "tripPlannerApproverID" => $tripPlannerApproverID, "finalApprover" => $finalApprover);
            $sql = "UPDATE tripPlannerApprovers SET gibbonPersonID=:gibbonPersonID, sequenceNumber=:sequenceNumber, finalApprover=:finalApprover, gibbonPersonIDUpdate=:gibbonPersonIDUpdate, timestampUpdate=:timestampUpdate WHERE tripPlannerApproverID=:tripPlannerApproverID";
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            $URL .= "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=error2";
            header("Location: {$URL}");
            exit();
        }
        $URL .= "trips_manageApprovers.php&return=success0";
        header("Location: {$URL}");
    }
}   
?>
