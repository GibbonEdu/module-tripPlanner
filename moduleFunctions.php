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
    	$sql="SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID";
    	$result=$connection2->prepare($sql);
    	$result->execute($data);
  	}
 	catch(PDOException $e) {
		print $e;
 	}

	return $result->fetch();
}

function getApprover($connection2, $tripPlannerApproverID) {
	try {
    	$data=array("tripPlannerApproverID" => $tripPlannerApproverID);
    	$sql="SELECT * FROM tripPlannerApprovers WHERE tripPlannerApproverID=:tripPlannerApproverID";
    	$result=$connection2->prepare($sql);
    	$result->execute($data);
  	}
 	catch(PDOException $e) {
		print $e;
 	}

	return $result->fetch();
}

function approverExists($connection2, $tripPlannerApproverID) {
	$approver = getApprover($connection2, $tripPlannerApproverID);
	return (count($approver) > 0);
}

?>