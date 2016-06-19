<?php

@session_start() ;

//Module includes
include "../../functions.php" ;
include "../../config.php" ;

include "./moduleFunctions.php" ;

date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL = $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/";

if(isset($_GET["tripPlannerApproverID"])) {
	if($_GET["tripPlannerApproverID"] != null && $_GET["tripPlannerApproverID"] != "") {
		$tripPlannerApproverID = $_GET["tripPlannerApproverID"];
	}
	else {
		$URL = $URL . "trips_manageApprovers.php&return=fail1";
		header("Location: {$URL}");
	}
}
else {
	$URL = $URL . "trips_manageApprovers.php&return=fail1";
	header("Location: {$URL}");
}

try {
	$connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
	$connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
	$URL = $URL . "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=fail1";
	header("Location: {$URL}");
}

if (isModuleAccessible($guid, $connection2)==FALSE) {
	//Acess denied
	$URL = $URL . "trips_manageApprovers.php&return=fail0";
	header("Location: {$URL}");
}
else {	

	if(isset($_POST["gibbonPersonID"])) {
		if($_POST["gibbonPersonID"] != null && $_POST["gibbonPersonID"] != "") {
			$gibbonPersonID = $_POST["gibbonPersonID"];
		}
	}
	else {
		$URL = $URL . "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=fail2";
		header("Location: {$URL}");
	}

	$expenseApprovalType=getSettingByScope($connection2, "Trip Planner", "requestApprovalType") ;
	if ($expenseApprovalType=="Chain Of All") {
		if(isset($_POST["sequenceNumber"])) {
			if($_POST["sequenceNumber"] != null && $_POST["sequenceNumber"] != "") {
				$sequenceNumber = abs($_POST["sequenceNumber"]);
			}
		}
		else {
			$URL = $URL . "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=fail2";
			header("Location: {$URL}");
		}
	}
	else {
		$sequenceNumber = 0;
	}

	try {
		if ($expenseApprovalType=="Chain Of All") {
			$approver = getApprover($connection2, $tripPlannerApproverID);
			if($approver['gibbonPersonID'] == $gibbonPersonID && $approver['sequenceNumber'] != $sequenceNumber) {
				$data=array("sequenceNumber"=>$sequenceNumber); 
				$sql="SELECT * FROM tripPlannerApprovers WHERE sequenceNumber=:sequenceNumber" ;
			}
			else if($approver['gibbonPersonID'] != $gibbonPersonID && $approver['sequenceNumber'] == $sequenceNumber) {
				$data=array("gibbonPersonID"=>$gibbonPersonID); 
				$sql="SELECT * FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID" ;
			}
			else {
				$data=array("gibbonPersonID"=>$gibbonPersonID, "sequenceNumber"=>$sequenceNumber); 
				$sql="SELECT * FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID OR sequenceNumber=:sequenceNumber" ;
			}
		}
		else {
			$data=array("gibbonPersonID"=>$gibbonPersonID); 
			$sql="SELECT * FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID" ;
		}
		$result=$connection2->prepare($sql);
		$result->execute($data);
	}
	catch(PDOException $e) { 
		//Fail 2
		$URL = $URL . "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=fail3";
		header("Location: {$URL}");
		break ;
	}
		
	if ($result->rowCount()>0 && approverExists($connection2, $tripPlannerApproverID)) {
		//Fail 4
		$URL = $URL . "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=fail4";
		header("Location: {$URL}");
	}
	else {	
		try {
			$data=array("gibbonPersonID"=> $gibbonPersonID, "sequenceNumber"=> $sequenceNumber, "gibbonPersonIDUpdate"=> $_SESSION[$guid]["gibbonPersonID"], "timestampUpdate"=>date('Y-m-d H:i:s', time()), "tripPlannerApproverID"=>$tripPlannerApproverID);
			$sql="UPDATE tripPlannerApprovers SET gibbonPersonID=:gibbonPersonID, sequenceNumber=:sequenceNumber, gibbonPersonIDUpdate=:gibbonPersonIDUpdate, timestampUpdate=:timestampUpdate WHERE tripPlannerApproverID=:tripPlannerApproverID" ;
			$result=$connection2->prepare($sql);
			$result->execute($data);
		}
		catch(PDOException $e) {
			$URL = $URL . "trips_editApprover.php&tripPlannerApproverID=$tripPlannerApproverID&return=fail5";
			header("Location: {$URL}");
			exit();
		}
		$URL = $URL . "trips_manageApprovers.php&return=success0";
		header("Location: {$URL}");
	}
}	
?>