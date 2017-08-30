<?php

@session_start();

//Module includes
include "../../functions.php";
include "../../config.php";

include "./moduleFunctions.php";

date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL = $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Trip Planner/trips_manageRiskTemplates.php";

$pdo = new Gibbon\sqlConnection();
$connection2 = $pdo->getConnection();

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manageRiskTemplates.php')) {
    //Acess denied
    $URL .= "&return=error0";
    header("Location: {$URL}");
    exit();
} else {
    if (isset($_GET["tripPlannerRiskTemplateID"])) {
        if ($_GET["tripPlannerRiskTemplateID"] != null && $_GET["tripPlannerRiskTemplateID"] != "") {
            $tripPlannerRiskTemplateID = $_GET["tripPlannerRiskTemplateID"];
        }
    } else {
        $URL .= "&return=error1";
        header("Location: {$URL}");
        exit();
    }

    try {
        $data = array("tripPlannerRiskTemplateID"=> $tripPlannerRiskTemplateID);
        $sql = "DELETE FROM tripPlannerRiskTemplates WHERE tripPlannerRiskTemplateID=:tripPlannerRiskTemplateID";
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        $URL .= "&return=error2";
        header("Location: {$URL}");
        exit();
    }

    $URL .= "&return=success0";
    header("Location: {$URL}");
    exit();
}   
?>
