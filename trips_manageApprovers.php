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

if (isModuleAccessible($guid, $connection2)==FALSE) {
	//Acess denied
	print "<div class='error'>" ;
		print "You do not have access to this action." ;
	print "</div>" ;
}
else {
	//New PDO DB connection. 
	//Gibbon uses PDO to connect to databases, rather than the PHP mysql classes, as they provide paramaterised connections, which are more secure.
	try {
		$connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
		$connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	}
	catch(PDOException $e) {
		echo $e->getMessage();
	}

	print "<h3>";
	print "Approvers";
	print "</h3>";

	$approvers = getApprovers($connection2);

	print "<div class='linkTop'>" ;
	print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/trips_addApprover.php'>" .  _('Add') . "<img style='margin-left: 5px' title='" . _('Add') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/page_new.png'/></a>" ;
	print "</div>" ;

	print "<table cellspacing='0' style='width: 100%'>" ;
    print "<tr class='head'>" ;
    	print "<th>" ;
        	print _("Name") ;
      	print "</th>" ;
      	$expenseApprovalType=getSettingByScope($connection2, "Trip Planner", "requestApprovalType") ;
	  	if ($expenseApprovalType=="Chain Of All") {
	  		print "<th>" ;
      			print _("Sequence Number") ;
      		print "</th>" ;
	  	}
      	print "<th>" ;
      		print _("Action") ;
      	print "</th>" ;
  	print "</tr>" ;
  	if(count($approvers) > 0) {
  		$rowCount=0;
  	  	foreach($approvers as $approver) {
  	  		if($rowCount%2 == 0) {
		 		print "<tr class='even'>";
		 	}
		  	else {
		 		print "<tr class='odd'>";
		  	}
	  			print "<td>";
	  				$name = getNameFromID($connection2, $approver['gibbonPersonID']);
	  				print $name['preferredName'] . " " . $name['surname'];
	  			print "</td>";
	  			$expenseApprovalType=getSettingByScope($connection2, "Trip Planner", "requestApprovalType") ;
				if ($expenseApprovalType=="Chain Of All") {
					print "<td>";
	  					print $approver['sequenceNumber'];
	  				print "</td>";
				}
	  			print "<td>";
	  				print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/trips_editApprover.php&tripPlannerApproverID=" . $approver["tripPlannerApproverID"] . "'><img title='" . _('Edit') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/config.png'/></a> " ;
					print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/modules/" . $_SESSION[$guid]["module"] . "/trips_deleteApproverProcess.php?tripPlannerApproverID=" . $approver["tripPlannerApproverID"] . "'><img title='" . _('Delete') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/garbage.png'/></a> " ;
	  			print "</td>";
	  		print "</tr>";
	  		$rowCount++;
	  	}
	}
	else {
		print "<tr>";
      		print "<td colspan= 4>";
       			print _("There are no records to display.");
      		print "</td>";
    	print "</tr>";
	}
  	print "</table>";

}	
?>