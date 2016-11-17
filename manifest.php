<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

//Basic variables
$name = "Trip Planner";
$description = "A trip planner module for Gibbon.";
$entryURL = "trips_manage.php";
$type = "Additional";
$category = "Learn"; 
$version = "0.0.04"; 
$author = "Ray Clark"; 
$url = "https://github.com/raynichc/Trip-Planner";

//Tables
$tables = 0;
$moduleTables[$tables++] = "CREATE TABLE `tripPlannerApprovers` (
    `tripPlannerApproverID` int(4) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `sequenceNumber` int(4) NULL,
    `gibbonPersonIDCreator` int(10) unsigned zerofill NOT NULL,
    `timestampCreator` timestamp NULL,
    `gibbonPersonIDUpdate` int(10) unsigned zerofill NULL,
    `timestampUpdate` timestamp NULL,
    `finalApprover` boolean DEFAULT 0 NULL,
    PRIMARY KEY (`tripPlannerApproverID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$moduleTables[$tables++] = "CREATE TABLE `tripPlannerRequests` (
    `tripPlannerRequestID` int(7) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `creatorPersonID` int(10) unsigned zerofill NOT NULL,
    `timestampCreation` timestamp,
    `title` varchar(60) NOT NULL,
    `description` text NOT NULL,
    `teacherPersonIDs` text NOT NULL,
    `studentPersonIDs` text NOT NULL,
    `location` text NOT NULL,
    `date` date NOT NULL,
    `startTime` time NOT NULL,
    `endTime` time NOT NULL,
    `riskAssessment` text NOT NULL,
    `status` ENUM('Requested', 'Approved', 'Rejected', 'Cancelled', 'Awaiting Final Approval') DEFAULT 'Requested' NOT NULL,
    `gibbonSchoolYearID` int(3) unsigned zerofill NOT NULL,
    `gibbonPersonIDUpdate` int(10) unsigned zerofill NULL,
    `timestampUpdate` timestamp NULL,
    PRIMARY KEY (`tripPlannerRequestID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$moduleTables[$tables++] = "CREATE TABLE `tripPlannerCostBreakdown` (
    `tripPlannerCostBreakdownID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `tripPlannerRequestID` int(7) unsigned zerofill NOT NULL,
    `title` varchar(60) NOT NULL,
    `description` text NOT NULL,
    `cost` decimal(12, 2) NOT NULL,
    PRIMARY KEY (`tripPlannerCostBreakdownID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$moduleTables[$tables++] = "CREATE TABLE `tripPlannerRequestLog` (
    `tripPlannerRequestLogID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `tripPlannerRequestID` int(7) unsigned zerofill NOT NULL,
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `action` ENUM('Request', 'Cancellation', 'Approval - Partial', 'Approval - Final', 'Rejection', 'Comment') NOT NULL,
    `comment` text NULL,
    `timestamp` timestamp NULL,
    PRIMARY KEY (`tripPlannerRequestLogID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

$moduleTables[$tables++] = "INSERT INTO `gibbonSetting` (`gibbonSystemSettingsID`, `scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES
(NULL, 'Trip Planner', 'requestApprovalType', 'Request Approval Type', 'The type of approval that a trip request has to go through.', 'One Of'),
(NULL, 'Trip Planner', 'riskAssessmentTemplate', 'Risk Assessment Template', 'The template for the Risk Assessment.', ''),
(NULL, 'Trip Planner', 'missedClassWarningThreshold', 'Missed Class Warning Threshold', 'The threshold for displaying a warning that student has missed a class too many times. Set to 0 to disable warnings.', '5'),
(NULL, 'Trip Planner', 'riskAssessmentApproval', 'Risk Assessment Approval', 'If this is enabled the Risk Assessment becomes an optional field until the trip has gone through approval. After this a Final Approval is required before the trip becomes approved.', '1');";

$moduleTables[$tables++] = "CREATE TABLE `tripPlannerRequestPerson` (
    `tripPlannerRequestPersonID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `tripPlannerRequestID` int(7) unsigned zerofill NOT NULL,
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `role` ENUM('Student', 'Teacher') NOT NULL,
    PRIMARY KEY (`tripPlannerRequestPersonID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

//Actions
$actionCount = 0;

$actionRows[$actionCount]["name"] = "Manage Trips"; 
$actionRows[$actionCount]["precedence"] = "0"; 
$actionRows[$actionCount]["category"] = "";
$actionRows[$actionCount]["description"] = "Manage trips.";
$actionRows[$actionCount]["URLList"] = "trips_manage.php"; 
$actionRows[$actionCount]["entryURL"] = "trips_manage.php"; 
$actionRows[$actionCount]["defaultPermissionAdmin"] = "Y";
$actionRows[$actionCount]["defaultPermissionTeacher"] = "Y";
$actionRows[$actionCount]["defaultPermissionStudent"] = "N"; 
$actionRows[$actionCount]["defaultPermissionParent"] = "N";
$actionRows[$actionCount]["defaultPermissionSupport"] = "N"; 
$actionRows[$actionCount]["categoryPermissionStaff"] = "Y"; 
$actionRows[$actionCount]["categoryPermissionStudent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionParent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionOther"] = "N"; 
$actionCount++;

$actionRows[$actionCount]["name"] = "Manage Trips_full"; 
$actionRows[$actionCount]["precedence"] = "1"; 
$actionRows[$actionCount]["category"] = "";
$actionRows[$actionCount]["description"] = "Manage trips.";
$actionRows[$actionCount]["URLList"] = "trips_manage.php"; 
$actionRows[$actionCount]["entryURL"] = "trips_manage.php"; 
$actionRows[$actionCount]["defaultPermissionAdmin"] = "Y";
$actionRows[$actionCount]["defaultPermissionTeacher"] = "N";
$actionRows[$actionCount]["defaultPermissionStudent"] = "N"; 
$actionRows[$actionCount]["defaultPermissionParent"] = "N";
$actionRows[$actionCount]["defaultPermissionSupport"] = "N"; 
$actionRows[$actionCount]["categoryPermissionStaff"] = "Y"; 
$actionRows[$actionCount]["categoryPermissionStudent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionParent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionOther"] = "N"; 
$actionCount++;

$actionRows[$actionCount]["name"] = "Submit Request"; 
$actionRows[$actionCount]["precedence"] = "0"; 
$actionRows[$actionCount]["category"] = "";
$actionRows[$actionCount]["description"] = "Submit a trip request.";
$actionRows[$actionCount]["URLList"] = "trips_submitRequest.php"; 
$actionRows[$actionCount]["entryURL"] = "trips_submitRequest.php"; 
$actionRows[$actionCount]["defaultPermissionAdmin"] = "Y";
$actionRows[$actionCount]["defaultPermissionTeacher"] = "Y";
$actionRows[$actionCount]["defaultPermissionStudent"] = "N"; 
$actionRows[$actionCount]["defaultPermissionParent"] = "N";
$actionRows[$actionCount]["defaultPermissionSupport"] = "N"; 
$actionRows[$actionCount]["categoryPermissionStaff"] = "Y"; 
$actionRows[$actionCount]["categoryPermissionStudent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionParent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionOther"] = "N"; 
$actionCount++;

$actionRows[$actionCount]["name"] = "Submit Request_all"; 
$actionRows[$actionCount]["precedence"] = "1"; 
$actionRows[$actionCount]["category"] = "";
$actionRows[$actionCount]["description"] = "Submit a trip request.";
$actionRows[$actionCount]["URLList"] = "trips_submitRequest.php"; 
$actionRows[$actionCount]["entryURL"] = "trips_submitRequest.php"; 
$actionRows[$actionCount]["defaultPermissionAdmin"] = "Y";
$actionRows[$actionCount]["defaultPermissionTeacher"] = "N";
$actionRows[$actionCount]["defaultPermissionStudent"] = "N"; 
$actionRows[$actionCount]["defaultPermissionParent"] = "N";
$actionRows[$actionCount]["defaultPermissionSupport"] = "N"; 
$actionRows[$actionCount]["categoryPermissionStaff"] = "Y"; 
$actionRows[$actionCount]["categoryPermissionStudent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionParent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionOther"] = "N"; 
$actionCount++;

$actionRows[$actionCount]["name"] = "Manage Approvers_view"; 
$actionRows[$actionCount]["precedence"] = "0"; 
$actionRows[$actionCount]["category"] = "";
$actionRows[$actionCount]["description"] = "Manage trip approvers.";
$actionRows[$actionCount]["URLList"] = "trips_manageApprovers.php"; 
$actionRows[$actionCount]["entryURL"] = "trips_manageApprovers.php";
$actionRows[$actionCount]["defaultPermissionAdmin"] = "Y";
$actionRows[$actionCount]["defaultPermissionTeacher"] = "N";
$actionRows[$actionCount]["defaultPermissionStudent"] = "N"; 
$actionRows[$actionCount]["defaultPermissionParent"] = "N";
$actionRows[$actionCount]["defaultPermissionSupport"] = "N"; 
$actionRows[$actionCount]["categoryPermissionStaff"] = "Y"; 
$actionRows[$actionCount]["categoryPermissionStudent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionParent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionOther"] = "N";
$actionCount++;

$actionRows[$actionCount]["name"] = "Manage Approvers_add&edit"; 
$actionRows[$actionCount]["precedence"] = "1"; 
$actionRows[$actionCount]["category"] = "";
$actionRows[$actionCount]["description"] = "Manage trip approvers.";
$actionRows[$actionCount]["URLList"] = "trips_manageApprovers.php,trips_addApprover.php,trips_editApprover.php"; 
$actionRows[$actionCount]["entryURL"] = "trips_manageApprovers.php";
$actionRows[$actionCount]["defaultPermissionAdmin"] = "Y";
$actionRows[$actionCount]["defaultPermissionTeacher"] = "N";
$actionRows[$actionCount]["defaultPermissionStudent"] = "N"; 
$actionRows[$actionCount]["defaultPermissionParent"] = "N";
$actionRows[$actionCount]["defaultPermissionSupport"] = "N"; 
$actionRows[$actionCount]["categoryPermissionStaff"] = "Y"; 
$actionRows[$actionCount]["categoryPermissionStudent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionParent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionOther"] = "N";
$actionCount++;

$actionRows[$actionCount]["name"] = "Manage Approvers_full"; 
$actionRows[$actionCount]["precedence"] = "2"; 
$actionRows[$actionCount]["category"] = "";
$actionRows[$actionCount]["description"] = "Manage trip approvers.";
$actionRows[$actionCount]["URLList"] = "trips_manageApprovers.php,trips_addApprover.php,trips_editApprover.php,trips_deleteApproverProcess.php"; 
$actionRows[$actionCount]["entryURL"] = "trips_manageApprovers.php";
$actionRows[$actionCount]["defaultPermissionAdmin"] = "Y";
$actionRows[$actionCount]["defaultPermissionTeacher"] = "N";
$actionRows[$actionCount]["defaultPermissionStudent"] = "N"; 
$actionRows[$actionCount]["defaultPermissionParent"] = "N";
$actionRows[$actionCount]["defaultPermissionSupport"] = "N"; 
$actionRows[$actionCount]["categoryPermissionStaff"] = "Y"; 
$actionRows[$actionCount]["categoryPermissionStudent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionParent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionOther"] = "N";
$actionCount++;

$actionRows[$actionCount]["name"] = "Manage Trip Planner Settings"; 
$actionRows[$actionCount]["precedence"] = "0"; 
$actionRows[$actionCount]["category"] = "";
$actionRows[$actionCount]["description"] = "Manage Trip Planner Settings.";
$actionRows[$actionCount]["URLList"] = "trips_manageSettings.php"; 
$actionRows[$actionCount]["entryURL"] = "trips_manageSettings.php"; 
$actionRows[$actionCount]["defaultPermissionAdmin"] = "Y";
$actionRows[$actionCount]["defaultPermissionTeacher"] = "N";
$actionRows[$actionCount]["defaultPermissionStudent"] = "N"; 
$actionRows[$actionCount]["defaultPermissionParent"] = "N";
$actionRows[$actionCount]["defaultPermissionSupport"] = "N"; 
$actionRows[$actionCount]["categoryPermissionStaff"] = "Y"; 
$actionRows[$actionCount]["categoryPermissionStudent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionParent"] = "N"; 
$actionRows[$actionCount]["categoryPermissionOther"] = "N";
$actionCount++; 

//Hooks
//$hooks[0] = ""; //Serialised array to create hook and set options. See Hooks documentation online.
?>