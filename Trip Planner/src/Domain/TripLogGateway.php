<?php

namespace Gibbon\Module\TripPlanner\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class TripLogGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tripPlannerRequestLog';
    private static $primaryKey = 'tripPlannerRequestLogID';
    private static $searchableColumns = [];

    public function queryLogs(QueryCriteria $criteria) {
        $query = $this->newQuery()
        ->from($this->getTableName())
        ->leftJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID = tripPlannerRequestLog.gibbonPersonID')
        ->cols([
            'tripPlannerRequestLogID', 'tripPlannerRequestID', 'tripPlannerRequestLog.action', 'tripPlannerRequestLog.comment', 'tripPlannerRequestLog.timestamp',
            'tripPlannerRequestLog.gibbonPersonID', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname'
        ]);

        $criteria->addFilterRules([
            'tripPlannerRequestID' => function ($query, $tripPlannerRequestID) {
                return $query->where('tripPlannerRequestLog.tripPlannerRequestID = :tripPlannerRequestID')
                    ->bindValue('tripPlannerRequestID', $tripPlannerRequestID);
            },
            'action' => function ($query, $action) {
                return $query->where('tripPlannerRequestLog.action = :action')
                    ->bindValue('action', $action);
            },
            'gibbonPersonID' => function($query, $gibbonPersonID) {
                return $query->where('tripPlannerRequestLog.gibbonPersonID = :gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            }
        ]);

        return $this->runQuery($query, $criteria);
    }


}
