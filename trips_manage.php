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

	<h3>
		Requests
	</h3>
	<table cellspacing = '0' style = 'width: 100% !important'>
   		<tr>
    		<th>
    			Title<br/>
   				<span style='font-size: 85%; font-style: italic'><?php print _('Description'); ?></span>
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
	if (true) {
    	print "<tr>";
    	$colspan = 4;
    	print "<td colspan=$colspan>";
    	print _("There are no records to display.");
		print "</td>";
		print "</tr>";
    }
    else {
    	
	}
    print "</table>";

}	
?>