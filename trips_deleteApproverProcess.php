<?php

@session_start() ;

//Module includes
include "../../functions.php" ;
include "../../config.php" ;

include "./moduleFunctions.php" ;

date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL = $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/";

try {
	$connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
	$connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
	$URL = $URL . "trips_manageApprovers.php&return=fail1";
	header("Location: {$URL}");
}

if (isModuleAccessible($guid, $connection2)==FALSE) {
	//Acess denied
	$URL = $URL . "trips_manageApprovers.php&return=fail0";
	header("Location: {$URL}");
}
else {	

	if(isset($_GET["tripPlannerApproverID"])) {
		if($_GET["tripPlannerApproverID"] != null && $_GET["tripPlannerApproverID"] != "") {
			$tripPlannerApproverID = $_GET["tripPlannerApproverID"];
		}
	}
	else {
		$URL = $URL . "trips_manageApprovers.php&return=fail2";
		header("Location: {$URL}");
	}
		
	if (!approverExists($connection2, $tripPlannerApproverID)) {
		//Fail 4
		$URL = $URL . "trips_manageApprovers.php&return=fail4";
		header("Location: {$URL}");
	}
	else {	
		try {
			$data=array("tripPlannerApproverID"=> $tripPlannerApproverID);
			$sql="DELETE FROM tripPlannerApprovers WHERE tripPlannerApproverID=:tripPlannerApproverID" ;
			$result=$connection2->prepare($sql);
			$result->execute($data);
		}
		catch(PDOException $e) {
			$URL = $URL . "trips_manageApprovers.php&return=fail5";
			header("Location: {$URL}");
			exit();
		}
		$URL = $URL . "trips_manageApprovers.php&return=success0";
		header("Location: {$URL}");
	}
}	
?>