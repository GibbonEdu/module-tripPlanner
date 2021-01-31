<?php

use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\Form;
use Gibbon\Module\TripPlanner\Data\Setting;
use Gibbon\Module\TripPlanner\Domain\ApproverGateway;
use Gibbon\Module\TripPlanner\Domain\TripCostGateway;
use Gibbon\Module\TripPlanner\Domain\TripDayGateway;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Module\TripPlanner\Domain\TripLogGateway;
use Gibbon\Module\TripPlanner\Domain\TripPersonGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Psr\Container\ContainerInterface;

function getSettings($guid, $riskTemplateGateway) {
    $requestApprovalOptions = ['One Of', 'Two Of', 'Chain Of All'];
    return [
        (new Setting('requestApprovalType'))
            ->setRenderer(function ($data, $row) use ($requestApprovalOptions) {
                $row->addSelect($data['name'])
                    ->fromArray($requestApprovalOptions)
                    ->selected($data['value'])
                    ->setRequired(true);
            })
            ->setProcessor(function ($data) use ($requestApprovalOptions) {
                return in_array($data, $requestApprovalOptions) ? $data : false;
            }),
        (new Setting('riskAssessmentApproval'))
            ->setRenderer(function ($data, $row) {
                $row->addCheckBox($data['name'])
                    ->checked(boolval($data['value']));
            })
            ->setProcessor(function ($data) {
                //TODO: Update trip's status?
                return $data === null ? 0 : 1;
            }),
        (new Setting('defaultRiskTemplate'))
            ->setRenderer(function ($data, $row) use ($riskTemplateGateway) {
                $templates = array('-1' => 'None', '0' => 'Custom');

                $criteria = $riskTemplateGateway->newQueryCriteria()
                    ->sortBy(['name']);

                foreach ($riskTemplateGateway->queryTemplates($criteria) as $template) {
                    $templates[$template['tripPlannerRiskTemplateID']] = $template['name'];
                } 

                $row->addSelect($data['name'])
                    ->fromArray($templates)
                    ->selected($data['value'])
                    ->setRequired(true);
            })
            ->setProcessor(function ($data) use ($riskTemplateGateway) {
                $data = intval($data);

                if ($data > 0) {
                    if (!$riskTemplateGateway->exists($data)) {
                        return false;
                    }
                } else if ($data < -1) {
                    return false;
                }

                return $data;
            }),
        (new Setting('riskAssessmentTemplate'))
            ->setRow(false)
            ->setRenderer(function ($data, $col) use ($guid) {
                $col->addEditor($data['name'], $guid)
                    ->setValue($data['value'])
                    ->setRows(15);
            })
            ->setProcessor(function ($data) {
                return $data ?? '';
            }),
        (new Setting('expiredUnapprovedFilter'))
            ->setRenderer(function ($data, $row) {
                $row->addCheckBox($data['name'])
                    ->checked(boolval($data['value']));
            })
            ->setProcessor(function ($data) {
                return $data === null ? 0 : 1;
            }),
        (new Setting('letterToParentsTemplate'))
            ->setRow(false)
            ->setRenderer(function ($data, $col) use ($guid) {
                $col->addEditor($data['name'], $guid)
                    ->setValue($data['value'])
                    ->setRows(15);
            })
            ->setProcessor(function ($data) {
                return $data ?? '';
            })
    ];
}

function formatExpandableSection($title, $content) {
    $output = '';

    $output .= '<h6>' . $title . '</h6></br>';
    $output .= nl2brr($content);

    return $output;
}

function getStatuses() {
    return [
        'Requested',
        'Approved',
        'Rejected',
        'Cancelled', 
        'Awaiting Final Approval',
    ];
}

function hasAccess(ContainerInterface $container, $tripPlannerRequestID, $gibbonPersonID, $highestAction) {

    //Has full access?
    if ($highestAction == 'Manage Trips_full') {
        return true;
    }

    //Is Owner?
    $tripGateway = $container->get(TripGateway::class);
    $trip = $tripGateway->getByID($gibbonPersonID);

    if (!empty($trip) && $trip['creatorPersonID'] == $gibbonPersonID) {
        return true;
    }

    //Is Involved?
    $tripPersonGateway = $container->get(TripPersonGateway::class);

    if ($tripPersonGateway->isInvolved($tripPlannerRequestID, $gibbonPersonID)) {
        return true;
    }

    //Is Approver?
    $approverGateway = $container->get(ApproverGateway::class);

    //TODO: Check if needs to approve?
    if (empty($approverGateway->selectApproverByPerson($gibbonPersonID))) {
        return true;
    }

    //Is HOD?
    $departmentGateway = $container->get(DepartmentGateway::class);
    $headOfDepartments = array_column($departmentGateway->selectDepartmentsByPerson($gibbonPersonID, 'Coordinator')->fetchAll(), 'gibbonDepartmentID');
    $tripOwnerDepartments = array_column($departmentGateway->selectDepartmentsByPerson($trip['creatorPersonID']), 'gibbonDepartmentID');

    return !empty(array_intersect($headOfDepartments, $tripOwnerDepartments));
}

function needsApproval(ContainerInterface $container, $gibbonPersonID, $tripPlannerRequestID) {
    $tripGateway = $container->get(TripGateway::class);
    $trip = $tripGateway->getByID($tripPlannerRequestID);

    if (empty($trip)) {
        return false;
    }

    $approverGateway = $container->get(ApproverGateway::class);

    $approver = $approverGateway->selectApproverByPerson($gibbonPersonID);
    $isApprover = !empty($approver);
    $finalApprover = $isApprover ? $approver['finalApprover'] : false; 

    if ($trip['status'] == 'Requested' && $isApprover) {
        $settingGateway = $container->get(SettingGateway::class);
        $requestApprovalType = $settingGateway->getSettingByScope('Trip Planner', 'requestApprovalType');

        if ($requestApprovalType == 'Two Of') {
            //Check if the user has already approved
            $tripLogGateway = $container->get(TripLogGateway::class);
            $approval = $tripLogGateway->selectBy([
                'tripPlannerRequestID' => $trip['tripPlannerRequestID'],
                'gibbonPersonID' => $gibbonPersonID,
                'action' => 'Approval - Partial'
            ]);

            if ($approval->isNotEmpty()) {
                return false;
            }
        } else if ($requestApprovalType == 'Chain Of All') {
            //Check if user is in line to approve
            $nextApprover = $approverGateway->selectNextApprover($trip['tripPlannerRequestID']);
            if ($nextApprover->isNotEmpty()) {
                $nextApprover = $nextApprover->fetch();
                if ($gibbonPersonID != $nextApprover['gibbonPersonID']) {
                    return false;
                }
            } else {
                return false;
            }
        }
    } else if ($trip['status'] != 'Awaiting Final Approval' || !$finalApprover) {
        return false;
    }

    return true;
}

function getPersonBlock($guid, $connection2, $gibbonPersonID, $role, $numPerRow=5, $emergency=false, $medical=false)
{
    try {
        $data = array('gibbonPersonID' => $gibbonPersonID);
        $sql = 'SELECT title, surname, preferredName, image_240, emergency1Name, emergency1Number1, emergency1Number2, emergency1Relationship, emergency2Name, emergency2Number1, emergency2Number2, emergency2Relationship FROM gibbonPerson WHERE gibbonPersonID=:gibbonPersonID';
        $result = $connection2->prepare($sql);
        $result->execute($data);
    } catch (PDOException $e) {
        // echo "<div class='error'>".$e->getMessage().'</div>';
    }

    try {
        $dataFamily = array('gibbonPersonID' => $gibbonPersonID);
        $sqlFamily = 'SELECT * FROM gibbonFamily JOIN gibbonFamilyChild ON (gibbonFamily.gibbonFamilyID=gibbonFamilyChild.gibbonFamilyID) WHERE gibbonPersonID=:gibbonPersonID';
        $resultFamily = $connection2->prepare($sqlFamily);
        $resultFamily->execute($dataFamily);
    } catch (PDOException $e) {
    }

    if ($result->rowCount() == 1) {
        $row = $result->fetch();
        $width = 100.0 / $numPerRow;
        print "<td style='border: 1px solid #rgba (1,1,1,0); width:$width%; text-align: center; vertical-align: top'>";
            print "<div>";
                print getUserPhoto($guid, $row['image_240'], 75);
            print "</div>";
            print "<div><b>";
                print formatName($row['title'], $row['preferredName'], $row['surname'], $role);
            print "</b><br/></div>";
            if($emergency) {
                print "<div id='em$gibbonPersonID' style='font-size:11px'>";
                    if($resultFamily->rowCount() == 1) {
                        $rowFamily = $resultFamily->fetch();
                        try {
                            $dataMember = array('gibbonFamilyID' => $rowFamily['gibbonFamilyID']);
                            $sqlMember = 'SELECT * FROM gibbonFamilyAdult JOIN gibbonPerson ON (gibbonFamilyAdult.gibbonPersonID=gibbonPerson.gibbonPersonID) WHERE gibbonFamilyID=:gibbonFamilyID ORDER BY contactPriority, surname, preferredName';
                            $resultMember = $connection2->prepare($sqlMember);
                            $resultMember->execute($dataMember);
                        } catch (PDOException $e) {
                        }

                        while ($rowMember = $resultMember->fetch()) {
                            print "<b>" . formatName($rowMember['title'], $rowMember['preferredName'], $rowMember['surname'], 'Parent');
                            try {
                                $dataRelationship = array('gibbonPersonID1' => $rowMember['gibbonPersonID'], 'gibbonPersonID2' => $gibbonPersonID, 'gibbonFamilyID' => $rowFamily['gibbonFamilyID']);
                                $sqlRelationship = 'SELECT * FROM gibbonFamilyRelationship WHERE gibbonPersonID1=:gibbonPersonID1 AND gibbonPersonID2=:gibbonPersonID2 AND gibbonFamilyID=:gibbonFamilyID';
                                $resultRelationship = $connection2->prepare($sqlRelationship);
                                $resultRelationship->execute($dataRelationship);
                            } catch (PDOException $e) {
                            }
                            if ($resultRelationship->rowCount() == 1) {
                                $rowRelationship = $resultRelationship->fetch();
                                print " (" . $rowRelationship['relationship'] . ")";
                            }
                            print "</b><br/>";
                            for ($i = 1; $i < 5; ++$i) {
                                if ($rowMember['phone'.$i] != '') {
                                    if ($rowMember['phone'.$i.'Type'] != '') {
                                        print $rowMember['phone'.$i.'Type'].':</i> ';
                                    }
                                    if ($rowMember['phone'.$i.'CountryCode'] != '') {
                                        print '+'.$rowMember['phone'.$i.'CountryCode'].' ';
                                    }
                                    print __m($rowMember['phone'.$i]).'<br/>';
                                }
                            }
                        }
                    }
                    if($row["emergency1Name"] != "") {
                            print "<b>" . $row["emergency1Name"] . " (" . $row["emergency1Relationship"] . ")</b><br/>";
                            print $row["emergency1Number1"] . "<br/>";
                            print $row["emergency1Number2"] . "<br/>";
                    }
                    if($row["emergency2Name"] != "") {
                            print "<b>" . $row["emergency2Name"] . " (" . $row["emergency2Relationship"] . ")</b><br/>";
                            print $row["emergency2Number1"] . "<br/>";
                            print $row["emergency2Number2"];
                    }
                print "</div>";
            }
        print "</td>";
    }
}

function requestNotification($guid, $connection2, $tripPlannerRequestID, $gibbonPersonID, $action)
{
    $ownerOnly = true;

    if ($action == "Approved") {
        $message = __m('Your trip request has been fully approved.');
    } elseif ($action == "Awaiting Final Approval") {
        $message = __m('Your trip request is awaiting final approval.');
    } elseif ($action == "Rejected") {
        $message = __m('Your trip request has been rejected.');
    } else {
        $message = __m('Someone has commented on a trip request.');
        $ownerOnly = false;
    }

    if($ownerOnly) {
        $owner = getOwner($connection2, $tripPlannerRequestID);
        if($owner != $gibbonPersonID) {
            setNotification($connection2, $guid, $owner, $message, "Trip Planner", "/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID=" . $tripPlannerRequestID);
        }
    } else {
        try {
            $data = array('tripPlannerRequestID' => $tripPlannerRequestID);
            $sql = 'SELECT DISTINCT gibbonPersonID FROM tripPlannerRequestLog WHERE tripPlannerRequestLog.tripPlannerRequestID=:tripPlannerRequestID ORDER BY timestamp';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $e) {
            echo "<div class='error'>".$e->getMessage().'</div>';
        }

        while($row = $result->fetch()) {
            if($row["gibbonPersonID"] != $gibbonPersonID) {
                setNotification($connection2, $guid, $row["gibbonPersonID"], $message, "Trip Planner", "/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID=" . $tripPlannerRequestID);
            }
        }
    }
}

/**
*/
function renderTrip(ContainerInterface $container, $tripPlannerRequestID, $approveMode) {
    global $gibbon;

    $gibbonPersonID = $gibbon->session->get('gibbonPersonID');
    $moduleName = $gibbon->session->get('module');

    $tripGateway = $container->get(TripGateway::class);
    $trip = $tripGateway->getByID($tripPlannerRequestID);

    $link = $gibbon->session->get('absoluteURL') . '/modules/' . $moduleName . '/trips_request' . ($approveMode ? "Approve" : "View") . 'Process.php';
    $form = Form::create('tripForm', $link);
    $form->addHiddenValue('address', $gibbon->session->get('address'));
    $form->addHiddenValue('tripPlannerRequestID', $tripPlannerRequestID);

    //TODO: Add header actions
    if ($gibbonPersonID == $trip['creatorPersonID']) {
        //Edit
    }

    if ($approveMode) {
        //View
    } else if (needsApproval($container, $gibbonPersonID, $tripPlannerRequestID)) {
        //Approve
    }

    //TODO: Show/Hide
    $row = $form->addRow();
        $row->addHeading(__('Basic Information'));

    $row = $form->addRow();
        $row->addLabel('titleLabel', Format::bold(__('Title')));
        $row->addTextfield('title')
            ->readonly();

    $row = $form->addRow();
        $col = $row->addColumn();
            $col->addLabel('description', Format::bold(__('Description')));
            $col->addContent($trip['description']);

    $row = $form->addRow();
        $row->addLabel('locationLabel', Format::bold(__('Location')));
        $row->addTextfield('location')
            ->readonly();

    $row = $form->addRow();
        $row->addLabel('statusLabel', Format::bold(__('Status')));
        $row->addTextfield('status')
            ->readOnly();

    $row = $form->addRow();
        $row->addHeading(__('Date & Time'));

    $row = $form->addRow();
            
        $tripDayGateway = $container->get(tripDayGateway::class);
        $dayCriteria = $tripDayGateway->newQueryCriteria()
            ->filterBy('tripPlannerRequestID', $tripPlannerRequestID);

        $table = DataTable::create('dateTime');

        $table->addColumn('startDate', __('Start Date'))
            ->format(Format::using('date', ['startDate']));
        $table->addColumn('endDate', __('End Date'))
            ->format(Format::using('date', ['endDate']));
        $table->addColumn('startTime', __('Start Time'))
            ->format(function ($day) {
                return boolval($day['allDay']) ? __('All Day') : Format::time($day['startTime']);
            });
        $table->addColumn('endTime', __('End Time'))
            ->format(function ($day) {
                return boolval($day['allDay']) ? '' : Format::time($day['endTime']);
            });

        $row->addContent($table->render($tripDayGateway->queryTripDay($dayCriteria)));
        
    $row = $form->addRow();
        $row->addHeading(__('Risk Assessment & Communication'));

    $row = $form->addRow();
        $col = $row->addColumn();
            $col->addLabel('riskAssessment', Format::bold(__('Risk Assessment')));
            $col->addContent($trip['riskAssessment']);

    $row = $form->addRow();
        $col = $row->addColumn();
            $col->addLabel('letterToParents', Format::bold(__('Letter To Parents')));
            $col->addContent($trip['letterToParents']);   

    $row = $form->addRow();
        $row->addHeading(__('Participants'));

    $row = $form->addRow();
        $col = $row->addColumn();
            $col->addLabel('teachers', Format::bold(__('Teachers')));
            $col->addContent('');

    $row = $form->addRow();
        $col = $row->addColumn();
            $col->addLabel('students', Format::bold(__('Students')));
            $col->addContent('');

    $row = $form->addRow();
        $row->addHeading(__('Cost Breakdown'));

    $row = $form->addRow();

        $tripCostGateway = $container->get(TripCostGateway::class);
        $costCriteria = $tripCostGateway->newQueryCriteria()
            ->filterBy('tripPlannerRequestID', $tripPlannerRequestID);
        $tripCosts = $tripCostGateway->queryTripCost($costCriteria);

        $totalCost = array_sum($tripCosts->getColumn('cost'));

        $table = DataTable::create('costBreakdown');

        $table->addColumn('title', __('Name'));

        $table->addColumn('description', __('Description'));

        $table->addColumn('cost', __('Cost'))
            ->format(Format::using('currency', ['cost']));

        $row->addContent($table->render($tripCosts));

    $row = $form->addRow();
        $row->addLabel('totalCostLabel', Format::bold(__('Total Cost')));
        $row->addTextfield('totalCost')
            ->setValue(Format::currency($totalCost))
            ->readOnly();

    $row = $form->addRow();
        $row->addHeading(__('Log'));

    $row = $form->addRow();

        $tripLogGateway = $container->get(TripLogGateway::class);
        $logCiteria = $tripLogGateway->newQueryCriteria()
            ->filterBy('tripPlannerRequestID', $tripPlannerRequestID)
            ->sortBy(['timestamp']);

        $table = DataTable::create('logs');

        $table->addExpandableColumn('contents')
            ->format(function ($log) {
                $output = '';

                if (!empty($log['comment'])) {
                    $output .= formatExpandableSection(__('Comment'), $log['comment']);
                }

                return $output;
            });

        $table->addColumn('person', __('Person'))
            ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, true]));

        $table->addColumn('timestamp', __('Date & Time'))
            ->format(Format::using('dateTime', ['timestamp']));

        $table->addColumn('action', __('Event'));

        $row->addContent($table->render($tripLogGateway->queryLogs($logCiteria)));

    if ($approveMode) {
        $row = $form->addRow();
            $row->addLabel('action', __('Action'));
            $row->addSelect('action')
                ->fromArray(['Approval', 'Rejection', 'Comment']);
    }

    $row = $form->addRow();
        $col = $row->addColumn();
            $col->addLabel('comment', __('Comment'));
            $col->addTextarea('comment');

    $row = $form->addRow();
        $row->addSubmit();

    $form->loadAllValuesFrom($trip);
    echo $form->getOutput();

    return;
        /*
            ?>
            <form method="post" action="<?php echo $link ?>">
                    <tbody id="peopleInfo">
                        <tr>
                            <td colspan=2>
                                <b><?php echo __m('Teachers') ?></b>
                                <table class='noIntBorder' cellspacing='0' style='width:100%;'>
                                    <tr>
                                        <?php
                                            $teacherCount = count($teachers);
                                            $teacherCount += 5 - ($teacherCount % 5);
                                            for ($i = 0; $i < $teacherCount; $i++) {
                                                if ($i % 5 == 0) {
                                                    print "</tr>";
                                                    print "<tr>";
                                                }
                                                if (isset($teachers[$i])) {
                                                    getPersonBlock($guid, $connection2, $teachers[$i], "Staff");
                                                } else {
                                                    print "<td>";
                                                    print "</td>";
                                                }
                                            }
                                        ?>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td colspan=2>
                                <?php if (!empty($students)) { ?>
                                <b><?php echo __m('Students') ?></b>
                                <?php
                                    echo "<div class='linkTop'>";
                                        echo "<a target='_blank' href='".$_SESSION[$guid]['absoluteURL']."/report.php?q=/modules/Trip Planner/trips_reportTripPeople.php&tripPlannerRequestID=$tripPlannerRequestID'>".__m('Student List')."<img style='margin-right: 10px;margin-left: 5px' title='".__m('Student List')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/print.png'/></a>";
                                        echo "<a target='_blank' href='".$_SESSION[$guid]['absoluteURL']."/report.php?q=/modules/Trip Planner/trips_reportTripOverview.php&tripPlannerRequestID=$tripPlannerRequestID&format=print&orientation=L'>".__m('Trip Overview')."<img style='margin-left: 5px' title='".__m('Student List')."' src='./themes/".$_SESSION[$guid]['gibbonThemeName']."/img/print.png'/></a>";

                                    echo '</div>';
                                ?>
                                <table class='noIntBorder' cellspacing='0' style='width:100%;'>
                                    <tr>
                                        <?php
                                            $numPerRow = 5;
                                            $studentCount = count($students);
                                            $studentCount += $numPerRow - ($studentCount % $numPerRow);
                                            for ($i = 0; $i < $studentCount; $i++) {
                                                if ($i % $numPerRow == 0) {
                                                    print "</tr>";
                                                    print "<tr>";
                                                }
                                                if (isset($students[$i])) {
                                                    getPersonBlock($guid, $connection2, $students[$i], "Student", $numPerRow);
                                                } else {
                                                    print "<td>";
                                                    print "</td>";
                                                }
                                            }
                                        ?>
                                    </tr>
                                </table>
                                <?php } else {
                                    print __("No students on this trip.");
                                } ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
            <?php
            */
}
?>
