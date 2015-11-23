<?php

function getApprovers($connection2) {
	try {
    	$data=array();
    	$sql="SELECT * FROM tripPlannerApprovers";
    	$result=$connection2->prepare($sql);
    	$result->execute($data);
  	}
 	catch(PDOException $e) {
		print $e;
 	}

	return $result->fetchAll();
}

function getNameFromID($connection2, $gibbonPersonID) {
	try {
    	$data=array("gibbonPersonID" => $gibbonPersonID);
    	$sql="SELECT preferredName, lastName FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID";
    	$result=$connection2->prepare($sql);
    	$result->execute($data);
  	}
 	catch(PDOException $e) {
		print $e;
 	}

	return $result->fetch();
}

?>