<?php

@session_start();

//Module includes
include "../../functions.php";
include "../../config.php";

include "./moduleFunctions.php";

date_default_timezone_set($_SESSION[$guid]["timezone"]);

$URL = $_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/Trip Planner/trips_submitRequest.php";

$pdo = new Gibbon\sqlConnection();
$connection2 = $pdo->getConnection();

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_submitRequest.php')) {
    //Acess denied
    $URL .= "&return=error0";
    header("Location: {$URL}");
} else {    
    $date = new DateTime();
    $items = array("title", "description", "date", "startTime", "endTime", "location", "riskAssessment", "teachersSelected", "studentsSelected", "totalCost", "order");
    $data = array("creatorPersonID" => $_SESSION[$guid]["gibbonPersonID"], "timestampCreation" => $date->format('Y-m-d H:i:s'), "gibbonSchoolYearID" => $_SESSION[$guid]["gibbonSchoolYearID"]);
    $sql = "INSERT INTO tripPlannerRequests SET creatorPersonID=:creatorPersonID, timestampCreation=:timestampCreation, gibbonSchoolYearID=:gibbonSchoolYearID, ";

    foreach ($items as $item) {
        if (isset($_POST[$item])) {
            if ($_POST[$item] != null && $_POST[$item] != "") {
                $key = $item;
                if ($item == "date") {
                    $date = DateTime::createFromFormat("d/m/Y", $_POST[$item]);
                    $data[$item] = $date->format("Y-m-d H:i:s");
                } elseif ($item == "teachersSelected" || $item == "studentsSelected") {
                    $arrayString = "";
                    foreach ($_POST[$item] as $person) {
                        $arrayString .= $person . ",";
                    }
                    $dataName = "teacherPersonIDs";
                    if ($item == "studentsSelected") {
                        $dataName = "studentPersonIDs";
                    } 
                    $data[$dataName] = substr($arrayString, 0, -1);
                    $key = $dataName;
                } elseif ($item == "order") {
                    $key = null;
                    $costs = array();
                    $order = $_POST['order'];
                    foreach ($order as $cost) {
                        $costs[$cost]['name'] = $_POST['name'.$cost];
                        $costs[$cost]['cost'] = $_POST['cost'.$cost];
                        $costs[$cost]['description'] = $_POST['description'.$cost];

                        if ($costs[$cost]['name'] == '' || $costs[$cost]['cost'] == '' || is_numeric($costs[$cost]['cost']) == false) {
                            $URL .= "&return=error1";
                            header("Location: {$URL}");
                            exit();
                        }
                    }
                } else {
                    $data[$item] = $_POST[$item];
                }

                if ($key != null) {
                    $sql .= $key . "=:" . $key . ", ";
                }
            }
        } else {
            $URL .= "&return=error1";
            header("Location: {$URL}");
            exit();
        }
    }

    $sql = substr($sql, 0, -2);

    try {
        $result = $connection2->prepare($sql);
        $result->execute($data);
        $tripPlannerRequestID = $connection2->lastInsertId();
        logEvent($connection2, $tripPlannerRequestID, $_SESSION[$guid]["gibbonPersonID"], "Request");
        $sql1 = "INSERT INTO tripPlannerCostBreakdown SET tripPlannerRequestID=:tripPlannerRequestID, title=:name, cost=:cost, description=:description";
        foreach ($costs as $cost) {
            $cost['tripPlannerRequestID'] = $tripPlannerRequestID;
            $result1 = $connection2->prepare($sql1);
            $result1->execute($cost);
        }
    } catch (PDOException $e) {
        print $e;
        $URL .= "&return=error2";
        //header("Location: {$URL}");
        exit();
    }

    $URL .= "&return=success0&tripPlannerRequestID=$tripPlannerRequestID";
    header("Location: {$URL}");
}   
?>