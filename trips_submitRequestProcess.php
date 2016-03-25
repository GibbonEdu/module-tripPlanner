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
	$URL = $URL . "trips_submitRequest.php&addReturn=fail1";
	header("Location: {$URL}");
}

if (isModuleAccessible($guid, $connection2)==FALSE) {
	//Acess denied
	$URL = $URL . "trips_submitRequest.php&addReturn=fail0";
	header("Location: {$URL}");
}
else {	

	$items = array("title", "description", "date", "startTime", "startTime", "location", "riskAssessment");
	$data = array("creatorPersonID"=>$_SESSION[$guid]["gibbonPersonID"], "timestampCreation"=>date('Y-m-d H:i:s', time()));

	foreach($items as $item) {
		if(isset($_POST[$item])) {
			if($_POST[$item] != null && $_POST[$item] != "") {
				${$item} = $_POST[$item];
				$data[$item] = ${$item};
			}
		}
		else {
			$URL = $URL . "trips_submitRequest.php&addReturn=fail2";
			header("Location: {$URL}");
		}
	}

	try {
		$sql="INSERT INTO tripPlannerRequests SET " ;
		for($i = 0; $i < count($data); $i++) {
			$key = array_keys($data)[$i];
			$sql.=$key . "=:" . $key . ", ";
		}
		$sql = substr($sql, 0, -2);
		// print $sql;
		$result=$connection2->prepare($sql);
		$result->execute($data);
	}
	catch(PDOException $e) {
		print $e;
		$URL = $URL . "trips_submitRequest.php&addReturn=fail3";
		header("Location: {$URL}");
		exit();
	}
	$URL = $URL . "trips_manage.php&addReturn=success0";
	header("Location: {$URL}");
}	
?>