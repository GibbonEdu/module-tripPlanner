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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

include "../../functions.php";
include "../../config.php";

include "./moduleFunctions.php";

//New PDO DB connection
$pdo = new Gibbon\sqlConnection();
$connection2 = $pdo->getConnection();

@session_start();

date_default_timezone_set($_SESSION[$guid]["timezone"]);

$gibbonCourseClassID = $_GET["gibbonCourseClassID"];
$type = $_GET["type"];
$typeVal = "false";
if ($type == "Add") {
    $typeVal = "true";
}

try {
    $data = array("gibbonCourseClassID" => $gibbonCourseClassID);
    $sql = "SELECT gibbonPersonID FROM gibbonCourseClassPerson WHERE gibbonCourseClassID=:gibbonCourseClassID AND role='Student'";
    $result = $connection2->prepare($sql);
    $result->execute($data);
    $students = array();
    while ($row = $result->fetch()) {
        $students[] = $row['gibbonPersonID'];
    }
    $js_array = json_encode($students);

    ?>
    <script type='text/javascript'>
        var students = <?php print $js_array ?>;
        var type = <?php print $typeVal ?>;
        for (var i = 0; i < allStudents.length; i++) {
            if(students.indexOf(allStudents[i].gibbonPersonID) >= 0) {
                allStudents[i].selected=type;
            }
        }
        resetArrays();
    </script>   
    <?php

} catch(PDOException $e) {
    exit();
}

?>