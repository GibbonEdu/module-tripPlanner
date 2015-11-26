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
	$URL = $URL . "trips_addApprovers.php&addReturn=fail1";
	header("Location: {$URL}");
}

if (isModuleAccessible($guid, $connection2)==FALSE) {
	//Acess denied
	$URL = $URL . "trips_manageApprovers.php&addReturn=fail0";
	header("Location: {$URL}");
}
else {	

	if(isset($_GET["tripPlannerApproverID"])) {
		if($_GET["tripPlannerApproverID"] != null && $_GET["tripPlannerApproverID"] != "") {
			$tripPlannerApproverID = $_GET["tripPlannerApproverID"];
		}
	}

	if(isset($_POST["gibbonPersonID"])) {
		if($_POST["gibbonPersonID"] != null && $_POST["gibbonPersonID"] != "") {
			$gibbonPersonID = $_POST["gibbonPersonID"];
		}
	}
	else {
		$URL = $URL . "trips_addApprovers.php&addReturn=fail2";
		header("Location: {$URL}");
	}

	try {
		// if ($expenseApprovalType=="Chain Of All") {
		// 	$data=array("gibbonPersonID"=>$gibbonPersonID, "sequenceNumber"=>$sequenceNumber); 
		// 	$sql="SELECT * FROM gibbonFinanceExpenseApprover WHERE gibbonPersonID=:gibbonPersonID OR sequenceNumber=:sequenceNumber" ;
		// }
		// else {
			$data=array("gibbonPersonID"=>$gibbonPersonID); 
			$sql="SELECT * FROM tripPlannerApprovers WHERE gibbonPersonID=:gibbonPersonID" ;
		// }
		$result=$connection2->prepare($sql);
		$result->execute($data);
	}
	catch(PDOException $e) { 
		//Fail 2
		$URL = $URL . "trips_addApprovers.php&addReturn=fail3";
		header("Location: {$URL}");
		break ;
	}
		
	if ($result->rowCount()>0 && approverExists($connection2, $tripPlannerApproverID)) {
		//Fail 4
		$URL = $URL . "trips_addApprovers.php&addReturn=fail4";
		header("Location: {$URL}");
	}
	else {	
		try {
			$data=array("gibbonPersonID"=> $gibbonPersonID, "sequenceNumber"=> 0, "gibbonPersonIDUpdate"=> $_SESSION[$guid]["gibbonPersonID"], "timestampUpdate"=>date('Y-m-d H:i:s', time()), "tripPlannerApproverID"=>$tripPlannerApproverID);
			$sql="UPDATE tripPlannerApprovers SET gibbonPersonID=:gibbonPersonID, sequenceNumber=:sequenceNumber, gibbonPersonIDUpdate=:gibbonPersonIDUpdate, timestampUpdate=:timestampUpdate WHERE tripPlannerApproverID=:tripPlannerApproverID" ;
			$result=$connection2->prepare($sql);
			$result->execute($data);
		}
		catch(PDOException $e) {
			$URL = $URL . "trips_addApprovers.php&addReturn=fail5";
			header("Location: {$URL}");
			exit();
		}
		$URL = $URL . "trips_manageApprovers.php&addReturn=success0";
		header("Location: {$URL}");
	}
}	
?>