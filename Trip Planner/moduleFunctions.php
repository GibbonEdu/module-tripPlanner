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
use Gibbon\Tables\View\GridView;
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

    //Is Approver (and needs their approval)?
    if (needsApproval($container, $gibbonPersonID, $tripPlannerRequestID)) {
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
        $form->addHeaderAction('edit', __('Edit'))
            ->setURL('/modules/' . $moduleName . '/trips_submitRequest.php')
            ->addParam('tripPlannerRequestID', $tripPlannerRequestID)
            ->addParam('mode', 'edit')
            ->displayLabel();
    }

    if ($approveMode) {
        //View
        $form->addHeaderAction('view', __('View'))
            ->setURL('/modules/' . $moduleName . '/trips_requestView.php')
            ->addParam('tripPlannerRequestID', $tripPlannerRequestID)
            ->displayLabel();
    } else if (needsApproval($container, $gibbonPersonID, $tripPlannerRequestID)) {
        //Approve
        $form->addHeaderAction('approve', __('Approve'))
            ->setIcon('iconTick')
            ->setURL('/modules/' . $moduleName . '/trips_requestApprove.php')
            ->addParam('tripPlannerRequestID', $tripPlannerRequestID)
            ->displayLabel();
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

            $tripPersonGateway = $container->get(TripPersonGateway::class);
            $peopleCriteria = $tripPersonGateway->newQueryCriteria()
                ->filterBy('tripPlannerRequestID', $tripPlannerRequestID)
                ->filterBy('role', 'Teacher')
                ->sortBy(['surname', 'preferredName']);

            $gridRenderer = new GridView($container->get('twig'));
            $table = $container->get(DataTable::class)->setRenderer($gridRenderer);

            $table->addMetaData('gridClass', 'rounded-sm bg-blue-100 border py-2');
            $table->addMetaData('gridItemClass', 'w-1/2 sm:w-1/4 md:w-1/5 my-2 text-center');
            
            $table->addColumn('image_240')
                ->format(Format::using('userPhoto', ['image_240', 'sm', '']));
            
            $table->addColumn('name')
                ->setClass('text-xs font-bold mt-1')
                ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Student', false, false]));

            $col->addContent($table->render($tripPersonGateway->queryTripPeople($peopleCriteria)));

    $row = $form->addRow();
        $col = $row->addColumn();
            $col->addLabel('students', Format::bold(__('Students')));

            $peopleCriteria->filterBy('role', 'Student');

            $table->addHeaderAction('studentList', __('Student List'))
                ->setIcon('print')
                ->displayLabel()
                ->setURL('/report.php')
                ->directLink()
                ->addParam('q', '/modules/' . $moduleName . '/trips_reportTripPeople.php')
                ->addParam('tripPlannerRequestID', $tripPlannerRequestID);

            $table->addHeaderAction('tripOverview', __('Trip Overview'))
                ->setIcon('print')
                ->displayLabel()
                ->setURL('/report.php')
                ->directLink()
                ->addParam('q', '/modules/' . $moduleName . '/trips_reportTripOverview.php')
                ->addParam('tripPlannerRequestID', $tripPlannerRequestID)
                ->addParam('format', 'print')
                ->addParam('orientation', 'L');

            $col->addContent($table->render($tripPersonGateway->queryTripPeople($peopleCriteria)));

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
}
?>
