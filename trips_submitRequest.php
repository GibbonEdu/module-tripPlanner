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
	print "<div class='trailHead'><a href='" . $_SESSION[$guid]["absoluteURL"] . "'>" . _("Home") . "</a> > <a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_GET["q"]) . "/" . getModuleEntry($_GET["q"], $connection2, $guid) . "'>" . _(getModuleName($_GET["q"])) . "</a> > </div><div class='trailEnd'>" . _('Submit Trip Request') . "</div>" ;
	print "</div>" ;

	print "<h3>";
	print "Request";
	print "</h3>";
	?>

	<form method="post" action="<?php print $_SESSION[$guid]["absoluteURL"] . "/modules/" . $_SESSION[$guid]["module"] . "/trips_submitRequestProcess.php" ?>">
		<table class='smallIntBorder' cellspacing='0' style="width: 100%">
			<tr>
				<td style='width: 275px'>
					<b><?php print _('Title') ?> *</b><br/>
				</td>
				<td class="right">
					<input name="title" id="title" maxlength=60 value="" type="text" style="width: 300px">
					<script type="text/javascript">
						var title=new LiveValidation('title');
						title.add(Validate.Presence);
					</script>
				</td>
			</tr>
			<tr> 
				<td style='width: 275px'>
					<b><?php print _('Description') ?> *</b><br/>
				</td>
				<td colspan=2>
					<textarea name='description' id='description' maxlength=1000 rows=5 style='width: 300px'></textarea>
					<script type="text/javascript">
						var description=new LiveValidation('description');
						description.add(Validate.Presence);
					</script>

				</td>
			</tr>
			<tr>
				<td> 
					<b><?php print _('Date') ?> *</b><br/>
					<span style="font-size: 90%"><i><?php print $_SESSION[$guid]["i18n"]["dateFormat"]  ?></i></span>
				</td>
				<td class="right">
					<input name="date" id="date" maxlength=10 value="" type="text" style="width: 300px">
					<script type="text/javascript">
						var date=new LiveValidation('date');
						date.add(Validate.Presence);
						date.add( Validate.Format, {pattern: <?php if ($_SESSION[$guid]["i18n"]["dateFormatRegEx"]=="") {  print "/^(0[1-9]|[12][0-9]|3[01])[- /.](0[1-9]|1[012])[- /.](19|20)\d\d$/i" ; } else { print $_SESSION[$guid]["i18n"]["dateFormatRegEx"] ; } ?>, failureMessage: "Use <?php if ($_SESSION[$guid]["i18n"]["dateFormat"]=="") { print "dd/mm/yyyy" ; } else { print $_SESSION[$guid]["i18n"]["dateFormat"] ; }?>." } ); 
					</script>
					 <script type="text/javascript">
						$(function() {
							$( "#date" ).datepicker();
						});
					</script>
				</td>
			</tr>
			<tr>
				<td> 
					<b><?php print _('Start Time') ?> *</b><br/>
					<span style="font-size: 90%"><i><?php print _('Format: hh:mm (24hr)') ?><br/></i></span>
				</td>
				<td class="right">
					<input name="startTime" id="startTime" maxlength=5 value="" type="text" style="width: 300px">
					<script type="text/javascript">
						var startTime=new LiveValidation('startTime');
						startTime.add(Validate.Presence);
						startTime.add( Validate.Format, {pattern: /^(0[0-9]|[1][0-9]|2[0-3])[:](0[0-9]|[1-5][0-9])/i, failureMessage: "Use hh:mm" } ); 
					</script>
				</td>
			</tr>
			<tr>
				<td> 
					<b><?php print _('End Time') ?> *</b><br/>
					<span style="font-size: 90%"><i><?php print _('Format: hh:mm (24hr)') ?><br/></i></span>
				</td>
				<td class="right">
					<input name="endTime" id="endTime" maxlength=5 value="" type="text" style="width: 300px">
					<script type="text/javascript">
						var endTime=new LiveValidation('endTime');
						endTime.add(Validate.Presence);
						endTime.add( Validate.Format, {pattern: /^(0[0-9]|[1][0-9]|2[0-3])[:](0[0-9]|[1-5][0-9])/i, failureMessage: "Use hh:mm" } ); 
					</script>
				</td>
			</tr>
			<tr> 
				<td style='width: 275px'>
					<b><?php print _('Location') ?> *</b><br/>
				</td>
				<td colspan=2>
					<input name="location" id="location" maxlength=60 value="" type="text" style="width: 300px">
					<script type="text/javascript">
						var loc=new LiveValidation('location');
						loc.add(Validate.Presence);
					</script>

				</td>
			</tr>
			<tr>
				<td colspan=2>
					<b><?php print _('Risk Assessment') ?> *</b><br/>
					<?php print getEditor($guid, TRUE, "riskAssessment", "", 5, true, true, false); ?>				
				</td>
			</tr>
			<tr>
				<td>
					<span style="font-size: 90%"><i>* <?php print _("denotes a required field") ; ?></i></span>
				</td>
				<td class="right">
					<input type="hidden" name="address" value="<?php print $_SESSION[$guid]["address"] ?>">
					<input type="submit" value="<?php print _("Submit") ; ?>">
				</td>
			</tr>
		</table>
	</form>
	<?php

}	
?>