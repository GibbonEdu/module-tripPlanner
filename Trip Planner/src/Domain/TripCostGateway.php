<?php

namespace Gibbon\Module\TripPlanner\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Module\TripPlanner\Domain\Traits\BulkInsert;

/**
 * Trip Cost Gateway
 */
class TripCostGateway extends QueryableGateway
{
    use BulkInsert;

    private static $tableName = 'tripPlannerCostBreakdown';
    private static $primaryKey = 'tripPlannerCostBreakdownID';
    private static $searchableColumns = [];

    public function queryTripCost(QueryCriteria $criteria) {
        $query = $this->newQuery()
        ->from($this->getTableName())
        ->cols([
            'tripPlannerCostBreakdownID', 'tripPlannerRequestID', 'title', 'description', 'cost'
        ]);

        $criteria->addFilterRules([
            'tripPlannerRequestID' => function ($query, $tripPlannerRequestID) {
                return $query->where('tripPlannerCostBreakdown.tripPlannerRequestID = :tripPlannerRequestID')
                    ->bindValue('tripPlannerRequestID', $tripPlannerRequestID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }
}
