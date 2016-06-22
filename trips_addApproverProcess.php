<?php

@session_start() ;

//Module includes
include "../../functions.php" ;
include "../../config.php" ;

include "./moduleFunctions.php" ;

date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL = $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/";

$pdo = new Gibbon\sqlConnection();
$connection2 = $pdo->getConnection();

if (isModuleAccessible($guid, $connection2)==FALSE) {
    //Acess denied
    $URL = $URL . "trips_manageApprover.php&return=error0";
    header("Location: {$URL}");
} else {    

    if(isset($_POST["gibbonPersonID"])) {
        if($_POST["gibbonPersonID"] != null && $_POST["gibbonPersonID"] != "") {
            $gibbonPersonID = $_POST["gibbonPersonID"];
        }
    } else {
        $URL .= "trips_addApprover.php&return=error1";
        header("Location: {$URL}");
    }

    $expenseApprovalType = getSettingByScope($connection2, "Trip Planner", "requestApprovalType") ;
    if ($expenseApprovalType == "Chain Of All") {
        if (isset($_POST["sequenceNumber"])) {
            if ($_POST["sequenceNumber"] != null && $_POST["sequenceNumber"] != "") {
                $sequenceNumber = abs($_POST["sequenceNumber"]);
            }
        } else {
            $URL .= "trips_addApprover.php&return=error1";
            header("Location: {$URL}");
        }
    } else {
        $sequenceNumber = 0;
    }

    try {
        $data = array("gibbonPersonID"=>$gibbonPersonID); 
        $sql = "SELECT * FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID" ;
        if ($expenseApprovalType == "Chain Of All") {
            $data["sequenceNumber"] = $sequenceNumber ;
            $sql .= " OR sequenceNumber=:sequenceNumber" ;
        }
        $result=$connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) { 
        //Fail 2
        $URL .= "trips_addApprover.php&return=error2";
        header("Location: {$URL}");
        exit();
    }
        
    if ($result->rowCount()>0) {
        //Fail 4
        $URL = $URL . "trips_addApprover.php&return=error5";
        header("Location: {$URL}");
    }
    else {  
        try {
            $data=array("gibbonPersonID"=> $gibbonPersonID, "sequenceNumber"=> $sequenceNumber, "gibbonPersonIDCreator"=> $_SESSION[$guid]["gibbonPersonID"], "timestampCreator"=>date('Y-m-d H:i:s', time()));
            $sql="INSERT INTO tripPlannerApprovers SET gibbonPersonID=:gibbonPersonID, sequenceNumber=:sequenceNumber, gibbonPersonIDCreator=:gibbonPersonIDCreator, timestampCreator=:timestampCreator" ;
            $result=$connection2->prepare($sql);
            $result->execute($data);
        }
        catch(PDOException $e) {
            $URL = $URL . "trips_addApprover.php&return=error2";
            header("Location: {$URL}");
            exit();
        }
        $URL = $URL . "trips_manageApprovers.php&return=success0";
        header("Location: {$URL}");
    }
}   
?>