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

/*
To whom it may concern,

I am very sorry that you have to go throught this file and try to fix or maintain it.
If I was in your position I would start from scratch and burn this file. Let no one
see it and most of all don't go crazy trying to figure out how it works.

Sincerly,
Ray
*/

@session_start();

//Module includes
include "./modules/Trip Planner/moduleFunctions.php";

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_submitRequest.php')) {
    //Acess denied
    print "<div class='error'>";
        print "You do not have access to this action.";
    print "</div>";
} else {
    print "<div class='trail'>";
        print "<div class='trailHead'><a href='" . $_SESSION[$guid]["absoluteURL"] . "'>" . _("Home") . "</a> > <a href='" . $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_GET["q"]) . "/" . getModuleEntry($_GET["q"], $connection2, $guid) . "'>" . _(getModuleName($_GET["q"])) . "</a> > </div><div class='trailEnd'>" . _('Submit Trip Request') . "</div>";
    print "</div>";

    if (isset($_GET['return'])) {
        $editLink = null;
        if(isset($_GET['tripPlannerRequestID'])) {
            $editLink = $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID=" . $_GET['tripPlannerRequestID'];
        }
        returnProcess($guid, $_GET['return'], $editLink, null);
    }

    print "<h3>";
        print "Request";
    print "</h3>";
    ?>

    <script type="text/javascript">
        function sort2DArrayJS(array, key) {
            array.sort(function(a, b) {
                var aVar = a.formName;
                var bVar = b.formName;
                var oAVar = a.studentName;
                var oBVar = b.studentName;
                if (key == "studentName") {
                    aVar = a.studentName;
                    bVar = b.studentName;
                    oAVar = a.formName;
                    oBVar = b.formName;
                }
                if (aVar > bVar) {
                    return 1;
                }

                if (aVar < bVar) {
                    return -1;
                }

                if(oAVar > oBVar) {
                    return 1;
                }

                if(oAVar < oBVar) {
                    return -1
                }

                return 0;
            });
            return array;
        }

        function optionTransfer(select0Name, select1Name, students) {
            var select0 = document.getElementById(select0Name);
            var select1 = document.getElementById(select1Name);
            for (var i = select0.length - 1; i>=0; i--) {
                var option = select0.options[i];
                if (option != null) {
                    if (option.selected) {
                        select0.remove(i);
                        try {
                            select1.add(option, null);
                        } catch (ex) {
                            select1.add(option);
                        }
                        if(students) {
                            var gibbonPersonID = option.value;
                            for (var i = 0; i < allStudents.length; i++) {
                                if(allStudents[i]['gibbonPersonID'] == gibbonPersonID) {
                                    allStudents[i]['selected'] = !allStudents[i]['selected'];
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            sortSelect(select0);
            sortSelect(select1);
        }

        function sortSelect(list) {
            var tempArray = new Array();
            for (var i=0;i<list.options.length;i++) {
                tempArray[i] = new Array();
                tempArray[i][0] = list.options[i].text;
                tempArray[i][1] = list.options[i].value;
            }
            tempArray.sort();
            while (list.options.length > 0) {
                list.options[0] = null;
            }
            for (var i=0;i<tempArray.length;i++) {
                var op = new Option(tempArray[i][0], tempArray[i][1]);
                list.options[i] = op;
            }
            return;
        }

        function submitForm() {
            var teachers = document.getElementById('teachers1');
            var students = document.getElementById('students1');
            var container = document.getElementById('finalData');
            for (var i = 0; i < Math.max(teachers.length, students.length); i++) {
                var teacher = teachers.options[i];
                var student = students.options[i];
                if (teacher != null) {
                    var teachersSelected = document.createElement("input");
                    teachersSelected.setAttribute('type', 'hidden');
                    teachersSelected.setAttribute('name', 'teachersSelected[]');
                    teachersSelected.setAttribute('value', teacher.value);
                    container.appendChild(teachersSelected);
                }

                if (student != null) {
                    var studentsSelected = document.createElement("input");
                    studentsSelected.setAttribute('type', 'hidden');
                    studentsSelected.setAttribute('name', 'studentsSelected[]');
                    studentsSelected.setAttribute('value', student.value);
                    container.appendChild(studentsSelected);
                }
            }
        }
    </script>

    <form method="post" name="requestForm" action="<?php print $_SESSION[$guid]["absoluteURL"] . "/modules/Trip Planner/trips_submitRequestProcess.php" ?>" onsubmit="submitForm(); return true;">
        <table class='smallIntBorder' cellspacing='0' style="width: 100%">
            <tr class='break'>
                <td colspan=2> 
                    <h3><?php print __($guid, 'Basic Information') ?></h3>
                </td>
            </tr>
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
                <td colspan=2>
                    <b><?php print _('Description') ?> *</b><br/>
                    <?php print getEditor($guid, TRUE, "description", "", 5, true, true, false); ?>               
                </td>
            </tr>
            <tr> 
                <td style='width: 275px'>
                    <b><?php print _('Location') ?> *</b><br/>
                </td>
                <td>
                    <input name="location" id="location" maxlength=60 value="" type="text" style="width: 300px">
                    <script type="text/javascript">
                        var loc=new LiveValidation('location');
                        loc.add(Validate.Presence);
                    </script>

                </td>
            </tr>
            <tr class='break'>
                <td colspan=2> 
                    <h3><?php print __($guid, 'Date & Time') ?></h3>
                </td>
            </tr>
            <tr>
                <td> 
                    <b><?php print _('Multiple Days') ?></b><br/>
                </td>
                <script type="text/javascript">
                    function adjustDays() {
                        var allDay = document.getElementById("multiDays");
                        if (allDay.checked) {
                            $("#endDateArea").slideDown("fast", $("#endDateArea").css("display","table-row"));

                        } else {
                            $('#endDateArea').css("display","none");
                        }
                    }
                </script>
                <td class="right">
                    <input type="checkbox" id="multiDays" value="multiDays" onchange="adjustDays()">
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
                        var date = new LiveValidation('date');
                        date.add(Validate.Presence);
                        date.add(Validate.Format, {pattern: <?php if ($_SESSION[$guid]["i18n"]["dateFormatRegEx"] == "") { print "/^(0[1-9]|[12][0-9]|3[01])[- /.](0[1-9]|1[012])[- /.](19|20)\d\d$/i"; } else { print $_SESSION[$guid]["i18n"]["dateFormatRegEx"]; } ?>, failureMessage: "Use <?php if ($_SESSION[$guid]["i18n"]["dateFormat"]=="") { print "dd/mm/yyyy"; } else { print $_SESSION[$guid]["i18n"]["dateFormat"]; }?>." } ); 
                    </script>
                    <script type="text/javascript">
                        $(function() {
                            $("#date").datepicker({
                                onClose: function () {
                                    this.focus();
                                }
                            });
                        });
                    </script>
                </td>
            </tr>
            <tr id="endDateArea" style="display:none;">
                <td> 
                    <b><?php print _('End Date') ?> *</b><br/>
                    <span style="font-size: 90%"><i><?php print $_SESSION[$guid]["i18n"]["dateFormat"]  ?></i></span>
                </td>
                <td class="right">
                    <input name="endDate" id="endDate" maxlength=10 value="" type="text" style="width: 300px">
                    <script type="text/javascript">
                        var endDate = new LiveValidation('endDate');
                        endDate.add(Validate.Format, {pattern: <?php if ($_SESSION[$guid]["i18n"]["dateFormatRegEx"] == "") { print "/^(0[1-9]|[12][0-9]|3[01])[- /.](0[1-9]|1[012])[- /.](19|20)\d\d$/i"; } else { print $_SESSION[$guid]["i18n"]["dateFormatRegEx"]; } ?>, failureMessage: "Use <?php if ($_SESSION[$guid]["i18n"]["dateFormat"]=="") { print "dd/mm/yyyy"; } else { print $_SESSION[$guid]["i18n"]["dateFormat"]; }?>." } ); 
                    </script>
                    <script type="text/javascript">
                        $(function() {
                            $("#endDate").datepicker({
                                onClose: function () {
                                    this.focus();
                                }
                            });
                        });
                    </script>
                </td>
            </tr>
            <tr>
                <td> 
                    <b><?php print _('All Day') ?></b><br/>
                </td>
                <script type="text/javascript">
                    function adjustTime() {
                        var allDay = document.getElementById("allDay");
                        if (allDay.checked) {
                            $('#startTimeArea').css("display","none");
                            $('#endTimeArea').css("display","none");
                        } else {
                            $("#startTimeArea").slideDown("fast", $("#startTimeArea").css("display","table-row"));
                            $("#endTimeArea").slideDown("fast", $("#endTimeArea").css("display","table-row"));
                        }
                    }
                </script>
                <td class="right">
                    <input type="checkbox" id="allDay" value="allDay" onchange="adjustTime()">
                </td>
            </tr>
            <tr id='startTimeArea'>
                <td> 
                    <b><?php print _('Start Time') ?> *</b><br/>
                    <span style="font-size: 90%"><i><?php print _('Format: hh:mm (24hr)') ?><br/></i></span>
                </td>
                <td class="right">
                    <input name="startTime" id="startTime" maxlength=5 value="" type="text" style="width: 300px">
                    <script type="text/javascript">
                        var startTime=new LiveValidation('startTime');
                        startTime.add( Validate.Format, {pattern: /^(0[0-9]|[1][0-9]|2[0-3])[:](0[0-9]|[1-5][0-9])/i, failureMessage: "Use hh:mm" } ); 
                    </script>
                </td>
            </tr>
            <tr id='endTimeArea'>
                <td> 
                    <b><?php print _('End Time') ?> *</b><br/>
                    <span style="font-size: 90%"><i><?php print _('Format: hh:mm (24hr)') ?><br/></i></span>
                </td>
                <td class="right">
                    <input name="endTime" id="endTime" maxlength=5 value="" type="text" style="width: 300px">
                    <script type="text/javascript">
                        var endTime=new LiveValidation('endTime');
                        endTime.add( Validate.Format, {pattern: /^(0[0-9]|[1][0-9]|2[0-3])[:](0[0-9]|[1-5][0-9])/i, failureMessage: "Use hh:mm" } ); 
                    </script>
                </td>
            </tr>
            
            <tr class='break'>
                <td colspan=2> 
                    <h3><?php print __($guid, 'Costs') ?></h3>
                </td>
            </tr>
            <?php 
                $type="cost"; 
            ?> 
            <style>
                #<?php print $type ?> { list-style-type: none; margin: 0; padding: 0; width: 100%; }
                #<?php print $type ?> div.ui-state-default { margin: 0 0px 5px 0px; padding: 5px; font-size: 100%; min-height: 58px; }
                div.ui-state-default_dud { margin: 5px 0px 5px 0px; padding: 5px; font-size: 100%; min-height: 58px; }
                html>body #<?php print $type ?> li { min-height: 58px; line-height: 1.2em; }
                .<?php print $type ?>-ui-state-highlight { margin-bottom: 5px; min-height: 58px; line-height: 1.2em; width: 100%; }
                .<?php print $type ?>-ui-state-highlight {border: 1px solid #fcd3a1; background: #fbf8ee url(images/ui-bg_glass_55_fbf8ee_1x400.png) 50% 50% repeat-x; color: #444444; }
            </style>
            <tr>
                <td colspan=2> 
                    <div class="cost" id="cost" style='width: 100%; padding: 5px 0px 0px 0px; min-height: 66px'>
                        <div id="costOuter0">
                            <div style='color: #ddd; font-size: 230%; margin: 15px 0 0 6px'><?php print __($guid, 'Costs will be listed here...') ?></div>
                        </div>
                    </div>
                    <div style='width: 100%; padding: 0px 0px 0px 0px'>
                        <div class="ui-state-default_dud" style='padding: 0px; height: 40px'>
                            <table class='blank' cellspacing='0' style='width: 100%'>
                                <tr>
                                    <td style='width: 50%'>
                                        <script type="text/javascript">
                                            var costCount=1;
                                        </script>
                                        <input type="button" value="New Cost" style='float: none; margin-left: 3px; margin-top: 0px; width: 350px' onclick="addCost()" />
                                        <?php
                                            $costBlock = "$('#cost').append('<div id=\"costOuter' + costCount + '\"><img style=\"margin: 10px 0 5px 0\" src=\"" . $_SESSION[$guid]["absoluteURL"] . "/themes/Default/img/loading.gif\" alt=\"Loading\" onclick=\"return false;\" /><br/>Loading</div>');";
                                            $costBlock .= "$(\"#costOuter\" + costCount).load(\"" . $_SESSION[$guid]["absoluteURL"] . "/modules/Trip%20Planner/trips_submitRequestAddBlockCostAjax.php\",\"id=\" + costCount);";
                                            $costBlock .= "costCount++;";
                                        ?>
                                        <script type='text/javascript'>
                                            function addCost() {
                                                $("#costOuter0").css("display", "none");
                                                <?php print $costBlock ?>
                                            }
                                        </script>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </td>
            </tr>
            <tr class='break'>
                <td colspan=2> 
                    <?php
                        $riskAssessmentApproval = getSettingByScope($connection2, "Trip Planner", "riskAssessmentApproval");
                    ?>
                    <h3><?php print __($guid, 'Risk Assessment & Communication') ?></h3>
                </td>
            </tr>
            <tr>
                <?php
                    try {
                        $sql = "SELECT value FROM gibbonSetting WHERE scope='Trip Planner' AND name='riskAssessmentTemplate'";
                        $result = $connection2->prepare($sql);
                        $result->execute();
                    } catch (PDOException $e) { 
                        print "<div class='error'>" . $e->getMessage() . "</div>"; 
                    }
                    $row = $result->fetch();
                ?>
                <td colspan=2>
                    <b><?php print _('Risk Assessment') . ($riskAssessmentApproval ? "" : "*") ?> </b></br>
                    <?php 
                        if ($riskAssessmentApproval) {
                            print "<span style='font-size: 90%'><i><?php print _('The Risk Assessment is not required until the trip is awaiting final approval.') ?><br/></i></span><br/>";
                        }
                    ?>
                    <?php print getEditor($guid, TRUE, "riskAssessment", $row["value"], 25, true, $riskAssessmentApproval, false); ?>               
                </td>
            </tr>
            <tr>
                <?php
                    try {
                        $sql = "SELECT value FROM gibbonSetting WHERE scope='Trip Planner' AND name='riskAssessmentTemplate'";
                        $result = $connection2->prepare($sql);
                        $result->execute();
                    } catch (PDOException $e) { 
                        print "<div class='error'>" . $e->getMessage() . "</div>"; 
                    }
                    $row = $result->fetch();
                ?>
                <td colspan=2>
                    <b><?php print _('Letter to Parents') ?></b></br>
                    <?php print getEditor($guid, TRUE, "letterToParents", "", 25, true, true, false); ?>               
                </td>
            </tr>
            <tr class='break'>
                <td colspan=2> 
                    <h3><?php print __($guid, 'Participants') ?></h3>
                </td>
            </tr>
            <tr>
                <td colspan=2>
                    <b><?php print _('Teachers') ?> *</b></br>
                    <select name='teachers' id='teachers' multiple style="width: 302px; height: 150px; margin-left: 0px !important; float: left;">
                        <?php
                            try {
                                $sql = "SELECT gibbonPersonID, preferredName, surname, title, gibbonRole.category FROM gibbonPerson JOIN gibbonRole ON (gibbonPerson.gibbonRoleIDPrimary = gibbonRole.gibbonRoleID) WHERE gibbonRole.category='Staff' AND gibbonPerson.status='Full' ORDER BY gibbonPerson.surname, gibbonPerson.preferredName ASC";
                                $result = $connection2->prepare($sql);
                                $result->execute();
                            } catch (PDOException $e) {
                            }

                            while (($row = $result->fetch()) != null) {
                                print "<option value='" . $row["gibbonPersonID"] . "'>" . formatName($row['title'], $row["preferredName"], $row["surname"], $row["category"], true, true) . "</option>";
                            }
                        ?>
                    </select>
                    <div style="float: left; width: 136px; height: 148px; display: table;">
                        <div style="display: table-cell; vertical-align: middle; text-align:center;">
                            <!-- <input id="teacherFilter" align="absmiddle" maxlength=60 value="" type="text" style="width: 73%; margin-right: 12.5%" onchange="filterTeachers()" /></br> -->
                            <input type="button" value="Add" style="width: 75%;" onclick="optionTransfer('teachers', 'teachers1', false)" /></br>
                            <input type="button" value="Remove" style="width: 75%;" onclick="optionTransfer('teachers1', 'teachers', false)" />
                        </div>
                    </div>
                    <select name='teachers1' id='teachers1' multiple style="float: right; margin-left: 0px !important; width: 302px; height: 150px;"></select>
                </td>
            </tr>
            <?php
                $allStudents = array();
                try {
                    $dataSelect = array('gibbonSchoolYearID' => $_SESSION[$guid]['gibbonSchoolYearID']);
                    $sqlSelect = "SELECT gibbonPerson.gibbonPersonID, preferredName, surname, gibbonRollGroup.name AS name FROM gibbonPerson, gibbonStudentEnrolment, gibbonRollGroup WHERE gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID AND gibbonStudentEnrolment.gibbonRollGroupID=gibbonRollGroup.gibbonRollGroupID AND status='FULL' AND (dateStart IS NULL OR dateStart<='".date('Y-m-d')."') AND (dateEnd IS NULL  OR dateEnd>='".date('Y-m-d')."') AND gibbonRollGroup.gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY name, surname, preferredName";
                    $resultSelect = $connection2->prepare($sqlSelect);
                    $resultSelect->execute($dataSelect);
                } catch (PDOException $e) {
                }
                while ($rowSelect = $resultSelect->fetch()) {
                    $student = array("selected" => false);
                    $student["studentName"] = formatName('', htmlPrep($rowSelect['preferredName']), htmlPrep($rowSelect['surname']), 'Student', true);
                    $student["formName"] = $rowSelect["name"];
                    $student["gibbonPersonID"] = $rowSelect["gibbonPersonID"];
                    $allStudents[] = $student;
                }
                $js_array = json_encode($allStudents);
            ?>
            <script type="text/javascript">
                var sortBy = "formName";
                var allStudents = <?php print $js_array; ?>;
                function changeSort() {
                    if (sortBy == "studentName") {
                        sortBy = "formName";
                        document.getElementById('studentSort').value = "Sort by Student";
                    } else {
                        sortBy = "studentName";
                        document.getElementById('studentSort').value = "Sort by Form";
                    }
                    allStudents = sort2DArrayJS(allStudents, sortBy);
                    resetArrays();
                }

                function resetArrays() {
                    var students = document.getElementById('students');
                    var students1 = document.getElementById('students1');
                    for(var i = Math.max(students.length, students1.length) - 1; i >=0 ; i--) {
                        if(students.options[i] != null) {
                            students.remove(i);
                        }

                        if(students1.options[i] != null) {
                            students1.remove(i);
                        }
                    }

                    for(var i = 0; i < allStudents.length; i++) {
                        var name = allStudents[i]['formName'] + " - " + allStudents[i]['studentName'];
                        if (sortBy == "studentName") {
                            name = allStudents[i]['studentName'] + " (" + allStudents[i]['formName'] + ")";
                        }
                        var op = new Option(name, allStudents[i]['gibbonPersonID']);
                        if (allStudents[i]['selected']) {
                            try {
                                students1.add(op, null);
                            } catch (ex) {
                                students1.add(op);
                            }
                        } else {
                            try {
                                students.add(op, null);
                            } catch (ex) {
                                students.add(op);
                            }
                        }
                    }
                }
            </script>
            <tr>
                <td colspan=2 style="border:none !important;">
                    <b><?php print _('Students') ?></b></br>
                    <select name='students' id='students' multiple style="width: 302px; height: 150px; margin-left: 0px !important; float: left;">
                        <?php
                            foreach ($allStudents as $student) {
                                print "<option value='" . $student["gibbonPersonID"] . "'>" . htmlPrep($student['formName']) . " - "  . $student["studentName"] . "</option>";
                            }
                        ?>
                    </select>
                    <div style="float: left; width: 136px; height: 148px; display: table; text-align:center;">
                        <div style="display: table-cell; vertical-align: middle;">
                            <input type="button" id='studentSort' value="Sort by Name" style="width: 80%;" onclick="changeSort()" /></br>
                            <input type="button" value="Add" style="width: 80%;" onclick="optionTransfer('students', 'students1', true)" /></br>
                            <input type="button" value="Remove" style="width: 80%;" onclick="optionTransfer('students1', 'students', true)" />
                        </div>
                    </div>
                    <select name='students1' id='students1' multiple style="float: right; margin-left: 0px !important; width: 302px; height: 150px;"></select>
                </td>
            </tr>
            <tr>
                <td>
                    <b><?php print _('Add Class to Students') ?></b></br>
                </td>
                <td class='right'>
                    <?php
                        $highestAction2 = getHighestGroupedAction($guid, '/modules/Trip Planner/trips_submitRequest.php', $connection2);
                    ?>
                    <select name="gibbonCourseClassID" id="gibbonCourseClassID" style="width: 302px;">
                        <option value="">No Class</option>
                        <?php
                        try {
                            if ($highestAction2 == 'Submit Request_all') {
                                $dataSelect = array('gibbonSchoolYearID' => $_SESSION[$guid]['gibbonSchoolYearID']);
                                $sqlSelect = 'SELECT gibbonCourseClassID, gibbonCourse.name, gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class FROM gibbonCourse JOIN gibbonCourseClass ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID) WHERE gibbonSchoolYearID=:gibbonSchoolYearID ORDER BY course, class';
                            } else {
                                $dataSelect = array('gibbonSchoolYearID' => $_SESSION[$guid]['gibbonSchoolYearID'], 'gibbonPersonID' => $_SESSION[$guid]['gibbonPersonID']);
                                $sqlSelect = "SELECT gibbonCourseClass.gibbonCourseClassID, gibbonCourse.name, gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class FROM gibbonCourse JOIN gibbonCourseClass ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID) JOIN gibbonCourseClassPerson ON (gibbonCourseClassPerson.gibbonCourseClassID=gibbonCourseClass.gibbonCourseClassID) WHERE role='Teacher' AND gibbonSchoolYearID=:gibbonSchoolYearID AND gibbonPersonID=:gibbonPersonID ORDER BY course, class";
                            }
                            $resultSelect = $connection2->prepare($sqlSelect);
                            $resultSelect->execute($dataSelect);
                        } catch (PDOException $e) {
                        }
                        while ($rowSelect = $resultSelect->fetch()) {
                            echo "<option value='".$rowSelect['gibbonCourseClassID']."'>".htmlPrep($rowSelect['course']).'.'.htmlPrep($rowSelect['class']).' - '.$rowSelect['name'].'</option>';
                        }
                        ?>
                    </select>
                    <div style="width:300px; float:right">

                        <script type="text/javascript">
                            function addClass(type) {
                                var gibbonCourseClassID = document.getElementById("gibbonCourseClassID").value;
                                if(gibbonCourseClassID != "") {
                                    $("#addClassDiv").load("<?php print $_SESSION[$guid]["absoluteURL"] . '/modules/Trip%20Planner/trips_submitRequestAddClassAjax.php'?>", "gibbonCourseClassID=" + gibbonCourseClassID + "&type=" + type);
                                }
                            }
                        </script>
                        <input type="button" value="Add" style="width: 145px; float:left;" onclick="addClass('Add')" /></br>
                        <input type="button" value="Remove" style="width: 145px; margin-top: -11px;" onclick="addClass('Remove')" />
                        
                    </div>
                    <div id='addClassDiv'>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <span style="font-size: 90%"><i>* <?php print _("denotes a required field"); ?></i></span>
                </td>
                <td class="right" id="finalData">
                    <input type="hidden" name="address" value="<?php print $_SESSION[$guid]["address"] ?>">
                    <input type="submit" value="<?php print _("Submit"); ?>">
                </td>
            </tr>
        </table>
    </form>
    <?php
}   
?>
