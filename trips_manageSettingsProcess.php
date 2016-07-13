<?php

@session_start();

//Module includes
include "../../functions.php";
include "../../config.php";

include "./moduleFunctions.php";

date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL = $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Trip Planner/trips_manageSettings.php";

$pdo = new Gibbon\sqlConnection();
$connection2 = $pdo->getConnection();

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manageSettings.php')) {
    //Acess denied
    $URL .= "&return=error0";
    header("Location: {$URL}");
} else {  

    if (isset($_POST["requestApprovalType"])) {
        if ($_POST["requestApprovalType"] != null && $_POST["requestApprovalType"] != "") {
            $requestApprovalType = $_POST["requestApprovalType"];
        }
    } else {
        $URL .= "&return=error1";
        header("Location: {$URL}");
    }

    try {
        $data=array("requestApprovalType" => $requestApprovalType);
        $sql="UPDATE gibbonSetting SET value=:requestApprovalType WHERE scope='Trip Planner' AND name='requestApprovalType';";
        $result=$connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        $URL .= "&return=error2";
        header("Location: {$URL}");
        exit();
    }

    if (isset($_POST["riskAssessmentTemplate"])) {
        if ($_POST["riskAssessmentTemplate"] != null && $_POST["riskAssessmentTemplate"] != "") {
            $riskAssessmentTemplate = $_POST["riskAssessmentTemplate"];
        }
    } else {
        $URL .= "&return=error1";
        header("Location: {$URL}");
    }

    try {
        $data=array("riskAssessmentTemplate" => $riskAssessmentTemplate);
        $sql="UPDATE gibbonSetting SET value=:riskAssessmentTemplate WHERE scope='Trip Planner' AND name='riskAssessmentTemplate';";
        $result=$connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        $URL .= "&return=error2";
        header("Location: {$URL}");
        exit();
    }
 
    $URL .= "&return=success0";
    header("Location: {$URL}");
}   
?>
