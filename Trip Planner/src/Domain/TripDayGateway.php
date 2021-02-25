<?php

namespace Gibbon\Module\TripPlanner\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Module\TripPlanner\Domain\Traits\BulkInsert;

/**
 * Trip Day Gateway
 */
class TripDayGateway extends QueryableGateway
{
    use BulkInsert;

    private static $tableName = 'tripPlannerRequestDays';
    private static $primaryKey = 'tripPlannerRequestDaysID';
    private static $searchableColumns = [];

    public function queryTripDay(QueryCriteria $criteria) {
        $query = $this->newQuery()
        ->from($this->getTableName())
        ->cols([
            'tripPlannerRequestDaysID', 'tripPlannerRequestID', 'startDate', 'endDate', 'allDay', 'startTime', 'endTime'
        ]);

        $criteria->addFilterRules([
            'tripPlannerRequestID' => function ($query, $tripPlannerRequestID) {
                return $query->where('tripPlannerRequestDays.tripPlannerRequestID = :tripPlannerRequestID')
                    ->bindValue('tripPlannerRequestID', $tripPlannerRequestID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }
}
