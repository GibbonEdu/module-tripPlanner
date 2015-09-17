<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2$actionCount1$actionCount, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

//Basic variables
$name="Trip Planner" ;
$description="A trip planner module for Gibbon." ;
$entryURL="trips_manage.php" ;
$type="Additional" ;
$category="Learn" ; 
$version="0.0.01" ; 
$author="Ray Clark" ; 
$url="https://github.com/raynichc/Trip-Planner" ;

//Tables
//$moduleTables[0]="" ;
//$moduleTables[1]="" ;

//Actions
$actionCount = 0;

$actionRows[$actionCount]["name"]="Manage Trips" ; 
$actionRows[$actionCount]["precedence"]="0"; 
$actionRows[$actionCount]["category"]="" ;
$actionRows[$actionCount]["description"]="Manage trips." ;
$actionRows[$actionCount]["URLList"]="trips_manage.php" ; 
$actionRows[$actionCount]["entryURL"]="trips_manage.php" ; 
$actionRows[$actionCount]["defaultPermissionAdmin"]="Y" ;
$actionRows[$actionCount]["defaultPermissionTeacher"]="Y" ;
$actionRows[$actionCount]["defaultPermissionStudent"]="N" ; 
$actionRows[$actionCount]["defaultPermissionParent"]="N" ;
$actionRows[$actionCount]["defaultPermissionSupport"]="N" ; 
$actionRows[$actionCount]["categoryPermissionStaff"]="Y" ; 
$actionRows[$actionCount]["categoryPermissionStudent"]="N" ; 
$actionRows[$actionCount]["categoryPermissionParent"]="N" ; 
$actionRows[$actionCount]["categoryPermissionOther"]="N" ; 
$actionCount++;

$actionRows[$actionCount]["name"]="Submit Trip Request" ; 
$actionRows[$actionCount]["precedence"]="0"; 
$actionRows[$actionCount]["category"]="" ;
$actionRows[$actionCount]["description"]="Submit a trip request." ;
$actionRows[$actionCount]["URLList"]="trips_submitRequest.php" ; 
$actionRows[$actionCount]["entryURL"]="trips_submitRequest.php" ; 
$actionRows[$actionCount]["defaultPermissionAdmin"]="Y" ;
$actionRows[$actionCount]["defaultPermissionTeacher"]="Y" ;
$actionRows[$actionCount]["defaultPermissionStudent"]="N" ; 
$actionRows[$actionCount]["defaultPermissionParent"]="N" ;
$actionRows[$actionCount]["defaultPermissionSupport"]="N" ; 
$actionRows[$actionCount]["categoryPermissionStaff"]="Y" ; 
$actionRows[$actionCount]["categoryPermissionStudent"]="N" ; 
$actionRows[$actionCount]["categoryPermissionParent"]="N" ; 
$actionRows[$actionCount]["categoryPermissionOther"]="N" ; 
$actionCount++;

$actionRows[$actionCount]["name"]="Manage Trip Approvers" ; 
$actionRows[$actionCount]["precedence"]="0"; 
$actionRows[$actionCount]["category"]="" ;
$actionRows[$actionCount]["description"]="Manage trip approvers." ;
$actionRows[$actionCount]["URLList"]="trips_manageApprovers.php" ; 
$actionRows[$actionCount]["entryURL"]="trips_manageApprovers.php" ; 
$actionRows[$actionCount]["defaultPermissionAdmin"]="Y" ;
$actionRows[$actionCount]["defaultPermissionTeacher"]="Y" ;
$actionRows[$actionCount]["defaultPermissionStudent"]="N" ; 
$actionRows[$actionCount]["defaultPermissionParent"]="N" ;
$actionRows[$actionCount]["defaultPermissionSupport"]="N" ; 
$actionRows[$actionCount]["categoryPermissionStaff"]="Y" ; 
$actionRows[$actionCount]["categoryPermissionStudent"]="N" ; 
$actionRows[$actionCount]["categoryPermissionParent"]="N" ; 
$actionRows[$actionCount]["categoryPermissionOther"]="N" ; 
//Hooks
//$hooks[0]="" ; //Serialised array to create hook and set options. See Hooks documentation online.
?>