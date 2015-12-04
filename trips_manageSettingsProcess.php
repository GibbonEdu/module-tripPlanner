<?php

@session_start() ;

//Module includes
include "../../functions.php" ;
include "../../config.php" ;

include "./moduleFunctions.php" ;

date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL = $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/trips_manageSettings.php";

try {
	$connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
	$connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
	$URL = $URL . "&addReturn=fail1";
	header("Location: {$URL}");
}

if (isModuleAccessible($guid, $connection2)==FALSE) {
	//Acess denied
	$URL = $URL . "&addReturn=fail0";
	header("Location: {$URL}");
}
else {	

	if(isset($_POST["requestApprovalType"])) {
		if($_POST["requestApprovalType"] != null && $_POST["requestApprovalType"] != "") {
			$requestApprovalType = $_POST["requestApprovalType"];
		}
	}
	else {
		$URL = $URL . "&addReturn=fail2";
		header("Location: {$URL}");
	}

	try {
		$data=array("requestApprovalType"=> $requestApprovalType);
		$sql="UPDATE gibbonSetting SET value=:requestApprovalType WHERE scope='Trip Planner' AND name='requestApprovalType';" ;
		$result=$connection2->prepare($sql);
		$result->execute($data);
	}
	catch(PDOException $e) {
		print $e;
		$URL = $URL . "&addReturn=fail5";
		header("Location: {$URL}");
		exit();
	}
	$URL = $URL . "&addReturn=success0";
	header("Location: {$URL}");
}	
?>