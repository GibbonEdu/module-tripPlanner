<?php
//USE ;end TO SEPERATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

//v0.0.01
$sql[$count][0]="0.0.01";
$sql[$count++][1]="-- First version, nothing to update";


//v0.0.02
$sql[$count][0]="0.0.02";
$sql[$count++][1]="
INSERT INTO gibbonSetting SET scope='Trip Planner', name='missedClassWarningThreshold', nameDisplay='Missed Class Warning Threshold', description='The threshold for displaying a warning that student has missed a class too many times. Set to 0 to disable warnings.', value='5';end
CREATE TABLE `tripPlannerRequestPerson` (`tripPlannerRequestPersonID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT, `tripPlannerRequestID` int(7) unsigned zerofill NOT NULL, `gibbonPersonID` int(10) unsigned zerofill NOT NULL, `role` ENUM('Student', 'Teacher') NOT NULL, PRIMARY KEY (`tripPlannerRequestPersonID`)) ENGINE=MyISAM DEFAULT CHARSET=utf8;end
";

$sql[$count][0]="0.0.03";
$sql[$count++][1]="
ALTER TABLE tripsPlannerRequests DROP COLUMN totalCost;end
INSERT INTO gibbonAction SET name='Submit Request_all', precedence=1, category='', description='Submit a trip request.', URLList='trips_submitRequest.php', entryURL='trips_submitRequest.php', defaultPermissionAdmin='Y', defaultPermissionTeacher='N', defaultPermissionStudent='N', defaultPermissionParent='N', defaultPermissionSupport='N', categoryPermissionStaff='Y', categoryPermissionStudent='N', categoryPermissionParent='N', categoryPermissionOther='N', gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Trip Planner');end
INSERT INTO `gibbonPermission` (`permissionID` ,`gibbonRoleID` ,`gibbonActionID`) VALUES (NULL , '1', (SELECT gibbonActionID FROM gibbonAction JOIN gibbonModule ON (gibbonAction.gibbonModuleID=gibbonModule.gibbonModuleID) WHERE gibbonModule.name='Trip Planner' AND gibbonAction.name='Submit Request_all'));end
";

?>