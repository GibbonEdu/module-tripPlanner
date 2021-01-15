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

use Gibbon\Module\TripPlanner\Domain\RiskTemplateGateway;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Risk Assessment Templates'));

if (!isActionAccessible($guid, $connection2, '/modules/Trip Planner/trips_manageRiskTemplates.php')) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    if (isset($_GET['return'])) {
        returnProcess($guid, $_GET['return'], null, null);
    }

    $moduleName = $gibbon->session->get('module');

    $riskTemplateGateway = $container->get(RiskTemplateGateway::class);

    $criteria = $riskTemplateGateway->newQueryCriteria()
        ->sortBy(['name'])
        ->fromPOST();

    $table = DataTable::createPaginated('risktemplates', $criteria);
    $table->setTitle(__('Risk Assessment Templates'));

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/' . $gibbon->session->get('module') . '/trips_addRiskTemplate.php')
        ->displayLabel();
    
    $table->addExpandableColumn('body')
        ->format(function ($riskTemplate) {
            $output = '';

            $output .= Format::bold(__('Risk Template Content')) . '<br/>';
            $output .= nl2brr($riskTemplate['body']) . '<br/>';

            return $output;
        });
    
    $table->addColumn('name', __('Risk Template Name'));

    $table->addActionColumn()
        ->addParam('tripPlannerRiskTemplateID')
        ->format(function ($riskTemplate, $actions) use ($moduleName) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/' . $moduleName . '/trips_editRiskTemplate.php');

            //TODO: Create Page
            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/' . $moduleName . '/trips_deleteRiskTemplate.php');
        });

    echo $table->render($riskTemplateGateway->queryTemplates($criteria));
}   
?>
