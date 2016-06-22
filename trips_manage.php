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
include "./modules/Trip Planner/moduleFunctions.php" ;

if (isModuleAccessible($guid, $connection2)==FALSE) {
	//Acess denied
	print "<div class='error'>" ;
		print "You do not have access to this action." ;
	print "</div>" ;
} else {
	print "<div class='trail'>" ;
		print "<div class='trailHead'><a href='" . $_SESSION[$guid]["absoluteURL"] . "'>" . _("Home") . "</a> > <a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_GET["q"]) . "/" . getModuleEntry($_GET["q"], $connection2, $guid) . "'>" . _(getModuleName($_GET["q"])) . "</a> > </div><div class='trailEnd'>" . _('Manage Trip Requests') . "</div>" ;
	print "</div>" ;

	?>
	<h3>
		Filter
	</h3>
	<?php
	print "<form method='post' action='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=" . $_GET["q"] . "'>" ; ?>
		<table class='noIntBorder' cellspacing='0' style='width: 100%'>
			<tr>
				<td class='right' colspan=2>
					<input type='submit' value='<?php print _('Go') ?>'>
				</td>
			</tr>
		</table>
	</form>

	<?php
		try {
	    	$data=array();
	    	$sql="SELECT tripPlannerRequests.tripPlannerRequestID, tripPlannerRequests.timestampCreation, tripPlannerRequests.title, tripPlannerRequests.description, tripPlannerRequests.status, gibbonPerson.preferredName, gibbonPerson.surname FROM tripPlannerRequests JOIN gibbonPerson ON tripPlannerRequests.creatorPersonID = gibbonPerson.gibbonPersonID";
	    	$result=$connection2->prepare($sql);
	    	$result->execute($data);
	  	}
	 	catch(PDOException $e) {
	 	}
	?>

	<h3>
		Requests
	</h3>
	<table cellspacing = '0' style = 'width: 100% !important'>
   		<tr>
    		<th>
    			Title
			</th>
			<th>
    			Description
			</th>
    		<th>
    			Owner
		  	</th>
		  	<th>
    			Status<br/>
   				<span style='font-size: 85%; font-style: italic'><?php print _('Date'); ?></span>
			</th>
   			<th>
   				Action
   			</th> 
   		</tr>
    <?php
	if ($result->rowCount() == 0) {?>
    	<tr>
    		<td colspan=5>
    			There are no records to display
			</td>
		</tr>
	<?php
    }
    else {
    	$rowCount=0;
    	while($row = $result->fetch()) {
    		if($rowCount % 2 == 0) {
    			print "<tr class='even'>";
    		}
    		else {
    			print "<tr class='odd'>";
    		}
	    		print "<td style='width:20%'>" . $row['title'] . "</td>";
	    		print "<td>" . $row['description'] . "</td>";
	    		print "<td style='width:20%'>" . $row['preferredName'] . " " . $row["surname"] . "</td>";
	    		print "<td style='width:12%'>";
	    			print $row['status'] . "</br>";
	    			print "<span style='font-size: 85%; font-style: italic'>" . dateConvertBack($guid, $row['timestampCreation']) . "</span>";    		
	    		print "</td>";
	    		print "<td style='width:11%'>";
	    			print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/trips_requestView.php&tripPlannerRequestID=" . $row["tripPlannerRequestID"] . "'><img title='" . _('View') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/plus.png'/></a> " ;
	    			print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/trips_requestEdit.php&tripPlannerRequestID=" . $row["tripPlannerRequestID"] . "'><img title='" . _('Edit') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/config.png'/></a> " ;
		    		if($row["status"] == "Requested") {
		    			if(needsApproval($connection2, $row["tripPlannerRequestID"], $_SESSION[$guid]["gibbonPersonID"])) {
		    				print "<a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . $_SESSION[$guid]["module"] . "/trips_requestApprove.php&tripPlannerRequestID=" . $row["tripPlannerRequestID"] . "'><img title='" . __($guid, 'Approve/Reject') . "' src='./themes/" . $_SESSION[$guid]["gibbonThemeName"] . "/img/iconTick.png'/></a> " ;
		    			}
		    		}
	    		print "</td>";
    		print "</tr>";
    	}
	}
	?>
    </table>
    <?php
}	
?>