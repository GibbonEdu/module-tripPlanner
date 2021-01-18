<?php

namespace Gibbon\Module\TripPlanner\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class TripPersonGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tripPlannerRequestPerson';
    private static $primaryKey = 'tripPlannerRequestPersonID';
    private static $searchableColumns = [];

    public function queryTripPeople(QueryCriteria $criteria) {
        $query = $this->newQuery()
        ->from($this->getTableName())
        ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID = tripPlannerRequestPerson.gibbonPersonID')
        ->cols([
            'gibbonPerson.gibbonPersonID', 'gibbonPerson.title', 'gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonPerson.image_240',
            'tripPlannerRequestPerson.tripPlannerRequestID', 'tripPlannerRequestPerson.role'
        ]);

        $criteria->addFilterRules([
            'tripPlannerRequestID' => function ($query, $tripPlannerRequestID) {
                return $query->where('tripPlannerRequestPerson.tripPlannerRequestID = :tripPlannerRequestID')
                    ->bindValue('tripPlannerRequestID', $tripPlannerRequestID);
            },
            'role' => function ($query, $role) {
                return $query->where('tripPlannerRequestPerson.role = :role')
                    ->bindValue('role', $role);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }
}
