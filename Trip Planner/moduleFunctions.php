<?php

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Tables\View\GridView;
use Psr\Container\ContainerInterface;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\TripPlanner\Data\Setting;
use Gibbon\Domain\Departments\DepartmentGateway;
use Gibbon\Module\TripPlanner\Domain\TripGateway;
use Gibbon\Module\TripPlanner\Data\SettingFactory;
use Gibbon\Module\TripPlanner\Domain\TripDayGateway;
use Gibbon\Module\TripPlanner\Domain\TripLogGateway;
use Gibbon\Module\TripPlanner\Domain\ApproverGateway;
use Gibbon\Module\TripPlanner\Domain\TripCostGateway;
use Gibbon\Module\TripPlanner\Domain\TripPersonGateway;
use Gibbon\Module\TripPlanner\Domain\RiskTemplateGateway;

function getSettings(ContainerInterface $container, $guid) {
    $riskTemplateGateway = $container->get(RiskTemplateGateway::class);
    $tripGateway = $container->get(TripGateway::class);

    $requestApprovalOptions = ['One Of', 'Two Of', 'Chain Of All'];

    $settingFactory = new SettingFactory();

    $settingFactory->addSetting('requestApprovalType')
        ->setRenderer(function ($data, $row) use ($requestApprovalOptions) {
            $row->addSelect($data['name'])
                ->fromArray($requestApprovalOptions)
                ->selected($data['value'])
                ->setRequired(true);
        })
        ->setProcessor(function ($data) use ($requestApprovalOptions) {
            return in_array($data, $requestApprovalOptions) ? $data : false;
        });

    $settingFactory->addSetting('riskAssessmentApproval')
        ->setRenderer(function ($data, $row) {
            $row->addCheckBox($data['name'])
                ->checked(boolval($data['value']));
        })
        ->setProcessor(function ($data) use ($tripGateway) {
            $enabled = $data !== null;

            if (!$enabled) {
                //TODO: Get trips that will be changed, send notification once changed

                $success = $tripGateway->updateWhere(
                    ['status' => 'Awaiting Final Approval'],
                    ['status' => 'Approved']
                );

                if (!$success) {
                    return false;
                }
            }

            return $enabled ? 1 : 0;
        });

    $settingFactory->addSetting('defaultRiskTemplate')
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
        });

    $settingFactory->addSetting('riskAssessmentTemplate')
        ->setRow(false)
        ->setRenderer(function ($data, $col) use ($guid) {
            $col->addEditor($data['name'], $guid)
                ->setValue($data['value'])
                ->setRows(15);
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    $settingFactory->addSetting('expiredUnapprovedFilter')
        ->setRenderer(function ($data, $row) {
            $row->addCheckBox($data['name'])
                ->checked(boolval($data['value']));
        })
        ->setProcessor(function ($data) {
            return $data === null ? 0 : 1;
        });

    $settingFactory->addSetting('letterToParentsTemplate')
        ->setRow(false)
        ->setRenderer(function ($data, $col) use ($guid) {
            $col->addEditor($data['name'], $guid)
                ->setValue($data['value'])
                ->setRows(15);
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    $settingFactory->addSetting('contactAddress')
        ->setRenderer(function ($data, $row) {
            $row->addTextArea($data['name'])
                ->setValue($data['value'])
                ->setRows(3);
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    $settingFactory->addSetting('contactPhone')
        ->setRenderer(function ($data, $row) {
            $row->addTextField($data['name'])
                ->setValue($data['value']);
        })
        ->setProcessor(function ($data) {
            return $data ?? '';
        });

    return $settingFactory->getSettings();
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
        'Pre-Approved',
    ];
}

function hasAccess(ContainerInterface $container, $tripPlannerRequestID, $gibbonPersonID, $highestAction) {

    //Has full access?
    if ($highestAction == 'Manage Trips_full') {
        return true;
    }

    //Has read-only access?
    if ($highestAction == 'Manage Trips_view') {
        return true;
    }

    //Is Owner?
    $tripGateway = $container->get(TripGateway::class);
    $trip = $tripGateway->getByID($tripPlannerRequestID);

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
    $tripOwnerDepartments = array_column($departmentGateway->selectDepartmentsByPerson($trip['creatorPersonID'])->fetchAll(), 'gibbonDepartmentID');

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

    if (($trip['status'] == 'Requested' || $trip['status'] == 'Pre-Approved') && $isApprover) {
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

function tripCommentNotifications($tripPlannerRequestID, $gibbonPersonID, $personName, $tripLogGateway, $trip, $comment, $notificationSender) {
    $text = __('{person} has commented on a trip request: {trip}', ['person' => $personName, 'trip' => $trip['title']]).'<br/><br/><b>'.__('Comment').':</b><br/>'.$comment;
    $notificationURL = '/index.php?q=/modules/Trip Planner/trips_requestView.php&tripPlannerRequestID=' . $tripPlannerRequestID;

    $people = $tripLogGateway->selectLoggedPeople($tripPlannerRequestID);
    while ($row = $people->fetch()) {
        //Skip current user
        if ($row['gibbonPersonID'] == $gibbonPersonID) continue;
        $notificationSender->addNotification($row['gibbonPersonID'], $text, 'Trip Planner', $notificationURL);
    }
}

/**
*/
function renderTrip(ContainerInterface $container, $tripPlannerRequestID, $approveMode, $readOnly = false, $showLogs = true, $highestAction = null) {
    global $gibbon;

    $gibbonPersonID = $gibbon->session->get('gibbonPersonID');
    $moduleName = $gibbon->session->get('module');

    $tripGateway = $container->get(TripGateway::class);
    $trip = $tripGateway->getByID($tripPlannerRequestID);

    $link = $gibbon->session->get('absoluteURL') . '/modules/' . $moduleName . '/trips_request' . ($approveMode ? "Approve" : "View") . 'Process.php';
    $form = Form::create('tripForm', $link);
    $form->addHiddenValue('address', $gibbon->session->get('address'));
    $form->addHiddenValue('tripPlannerRequestID', $tripPlannerRequestID);

    if ($gibbonPersonID == $trip['creatorPersonID'] or $highestAction == 'Manage Trips_full') {
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

    $on = './themes/'.$gibbon->session->get("gibbonThemeName").'/img/minus.png';
    $off = './themes/'.$gibbon->session->get("gibbonThemeName").'/img/plus.png';

    function toggleSection(&$row, $section, $icon) {
        $row->addWebLink(sprintf('<img title=%1$s src="%2$s" style="margin-right:4px;" />', __('Show/Hide'), $icon))
            ->setURL('#')
            ->onClick('toggleSection($(this), "'.$section.'"); return false;');
    }

    $row = $form->addRow();
        $row->addHeading(__('Basic Information'));
        toggleSection($row, 'basicInfo', $on);

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('titleLabel', Format::bold(__('Title')));
        $row->addTextfield('title')
            ->readonly();

    $row = $form->addRow()->addClass('basicInfo');
        $col = $row->addColumn();
            $col->addLabel('description', Format::bold(__('Description')));
            $col->addContent($trip['description']);

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('locationLabel', Format::bold(__('Location')));
        $row->addTextfield('location')
            ->readonly();

    $row = $form->addRow()->addClass('basicInfo');
        $row->addLabel('statusLabel', Format::bold(__('Status')));
        $row->addTextfield('status')
            ->readOnly();

    $row = $form->addRow();
        $row->addHeading(__('Date & Time'));
        toggleSection($row, 'dateTime', $on);

    $row = $form->addRow()->addClass('dateTime');

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
        toggleSection($row, 'riskAssess', $on);

    $row = $form->addRow()->addClass('riskAssess');
        $col = $row->addColumn();
            $col->addLabel('riskAssessment', Format::bold(__('Risk Assessment')));
            $col->addContent($trip['riskAssessment']);

    $row = $form->addRow()->addClass('riskAssess');
        $col = $row->addColumn();
            $col->addLabel('letterToParents', Format::bold(__('Letter To Parents')));
            $col->addContent($trip['letterToParents']);

    $row = $form->addRow();
        $row->addHeading(__('Participants'));
        toggleSection($row, 'participants', $on);

    $row = $form->addRow()->addClass('participants');
        $col = $row->addColumn();
            $col->addLabel('teachers', Format::bold(__('Teachers')));

            $tripPersonGateway = $container->get(TripPersonGateway::class);
            $peopleCriteria = $tripPersonGateway->newQueryCriteria()
                ->filterBy('tripPlannerRequestID', $tripPlannerRequestID)
                ->filterBy('role', 'Teacher')
                ->sortBy(['surname', 'preferredName'])
                ->pageSize(0);

            $gridRenderer = new GridView($container->get('twig'));
            $table = $container->get(DataTable::class)->setRenderer($gridRenderer);

            $table->addMetaData('gridClass', 'rounded-sm bg-blue-50 border py-2');
            $table->addMetaData('gridItemClass', 'w-1/2 sm:w-1/4 md:w-1/5 my-2 text-center');

            $table->addColumn('image_240')
                ->format(Format::using('userPhoto', ['image_240', 'sm', '']));

            $table->addColumn('name')
                ->setClass('text-xs font-bold mt-1')
                ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Staff', false, false]));

            $col->addContent($table->render($tripPersonGateway->queryTripPeople($peopleCriteria)));

    $row = $form->addRow()->addClass('participants');
        $col = $row->addColumn();
            $col->addLabel('students', Format::bold(__('Students')));

            $peopleCriteria = $tripPersonGateway->newQueryCriteria()
                ->filterBy('tripPlannerRequestID', $tripPlannerRequestID)
                ->filterBy('role', 'Student')
                ->sortBy(['surname', 'preferredName'])
                ->pageSize(0);

            $table = $container->get(DataTable::class)->setRenderer($gridRenderer);

            $table->addMetaData('gridClass', 'rounded-sm bg-blue-50 border py-2');
            $table->addMetaData('gridItemClass', 'w-1/2 sm:w-1/4 md:w-1/5 my-2 text-center');

            $table->addColumn('image_240')
                ->format(Format::using('userPhoto', ['image_240', 'sm', '']));

            $table->addColumn('name')
                ->setClass('text-xs font-bold mt-1')
                ->format(Format::using('name', ['title', 'preferredName', 'surname', 'Student', false, false]));

            $table->addHeaderAction('studentList', __('Student List'))
                ->setIcon('print')
                ->displayLabel()
                ->setURL('/report.php')
                ->directLink()
                ->setTarget('_blank')
                ->addParam('q', '/modules/' . $moduleName . '/trips_reportTripPeople.php')
                ->addParam('tripPlannerRequestID', $tripPlannerRequestID)
                ->append('&nbsp; | &nbsp;');

            $table->addHeaderAction('printableCards', __('Printable Contact Cards'))
                ->setIcon('print')
                ->displayLabel()
                ->setURL('/report.php')
                ->directLink()
                ->setTarget('_blank')
                ->addParam('q', '/modules/' . $moduleName . '/trips_reportPrintableCards.php')
                ->addParam('tripPlannerRequestID', $tripPlannerRequestID)
                ->addParam('format', 'print')
                ->addParam('orientation', 'L')
                ->addParam('hideHeader', true)
                ->append('&nbsp; | &nbsp;');

            $table->addHeaderAction('tripOverview', __('Trip Overview'))
                ->setIcon('print')
                ->displayLabel()
                ->setURL('/report.php')
                ->directLink()
                ->setTarget('_blank')
                ->addParam('q', '/modules/' . $moduleName . '/trips_reportTripOverview.php')
                ->addParam('tripPlannerRequestID', $tripPlannerRequestID)
                ->addParam('format', 'print')
                ->addParam('orientation', 'L');

            $col->addContent($table->render($tripPersonGateway->queryTripPeople($peopleCriteria)));

    $row = $form->addRow();
        $row->addHeading(__('Cost Breakdown'));
        toggleSection($row, 'costBreakdown', $on);

    $row = $form->addRow()->addClass('costBreakdown');

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

    $row = $form->addRow()->addClass('costBreakdown');
        $row->addLabel('totalCostLabel', Format::bold(__('Total Cost')));
        $row->addTextfield('totalCost')
            ->setValue(Format::currency($totalCost))
            ->readOnly();

    if ($showLogs) {

        $row = $form->addRow();
            $row->addHeading(__('Log'));
            toggleSection($row, 'logs', $on);

        $row = $form->addRow()->addClass('logs');

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
    }

    if ($approveMode) {
        $row = $form->addRow();
            $row->addLabel('action', __('Action'));
            $row->addSelect('action')
                ->fromArray(['Approval', 'Rejection', 'Comment', 'Pre-Approval']);
    }

    if (!$readOnly) {
        $row = $form->addRow();
            $col = $row->addColumn();
                $col->addLabel('comment', __('Comment'));
                $col->addTextarea('comment');

        $row = $form->addRow();
            $row->addSubmit();
    }

    $form->loadAllValuesFrom($trip);
    echo $form->getOutput();

    ?>
    <script type="text/javascript">
        function toggleSection(button, section) {
            var rows = $('.' + section);
            if (rows.hasClass('showHide')) {
                button.find('img').attr('src', '<?php echo $on ?>');
                rows.removeClass('showHide');
                rows.show();
            } else {
                button.find('img').attr('src', '<?php echo $off ?>');
                rows.addClass('showHide');
                rows.hide();
            }
        }
    </script>
    <?php
}
?>
