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

//$_POST['address'] = 'modules/Trip Planner/trips_submitRequestAddGroupAjax.php';

require_once '../../gibbon.php';

//Check proper inputs are given
if (!isset($_GET['mode']) || !isset($_GET['data'])) {
        exit();
}

//Process data and validate
$data = explode(':', $_GET['data']);
if (count($data) != 2) {
    exit();
}

$type = $data[0];
$id = $data[1];

$removing = $_GET['mode'] !== 'Add' ? 'true' : 'false';

try {
    //TODO: Migrate to Gateway
    $data = ['id' => $id];
    switch ($type) {
        case 'Class':
            $sql = "SELECT gibbonPersonID FROM gibbonCourseClassPerson 
                    WHERE gibbonCourseClassID=:id 
                    AND role='Student'";
            break;

        case 'Activity':
            $sql = "SELECT gibbonPersonID FROM gibbonActivityStudent 
                    WHERE gibbonActivityID=:id
                    AND status='Accepted'";
            break;

        case 'Group':
            $sql = "SELECT gibbonGroupPerson.gibbonPersonID FROM gibbonGroupPerson
                    JOIN gibbonPerson ON (gibbonGroupPerson.gibbonPersonID=gibbonPerson.gibbonPersonID) 
                    WHERE gibbonGroupID=:id 
                    AND gibbonPerson.status='Full'";
            break;

        default:
            exit();
    }

    $result = $connection2->prepare($sql);
    $result->execute($data);
    $students = array_column($result->fetchAll(), 'gibbonPersonID');
    ?>
    <script type='text/javascript'>
        //Student Data
        var students = <?php echo json_encode($students) ?>;
        //Get Selects
        var source = $('#studentsSource');
        var destination = $('#students');

        //Swap Selects if removing
        if (<?php echo $removing ?>) {
            var temp = destination;
            destination = source;
            source = temp;
        }

        //Move students from one select to toher
        for (var student of students) {
            var option = source.find('option[value="' + student + '"]');
            destination.append(option.clone());
            option.detach().remove();
        }

        sortSelects('students');
    </script>   

    <?php
} catch(PDOException $e) {
    exit();
}

?>