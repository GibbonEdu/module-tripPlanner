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

@session_start() ;

//Module includes
include "./modules/" . $_SESSION[$guid]["module"] . "/moduleFunctions.php" ;
$URL = "/modules/" . $_SESSION[$guid]["module"];

if (isModuleAccessible($guid, $connection2)==FALSE) {
	//Acess denied
	header("Location: $URL" . "trips_manageApprovers.php");
}
else {
	try {
		$connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
		$connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		header("Location: $URL" . "trips_addApprover.php");
	}

	if(isset($_POST["gibbonPersonID"])) {
		if($_POST["gibbonPersonID"] != null && $_POST["gibbonPersonID"] != "") {
			$gibbonPersonID = $_POST["gibbonPersonID"];
		}
	}

}	
?>