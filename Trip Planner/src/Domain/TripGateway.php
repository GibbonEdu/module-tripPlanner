<?php

namespace Gibbon\Module\TripPlanner\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class TripGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tripPlannerRequests';
    private static $primaryKey = 'tripPlannerRequestID';
    private static $searchableColumns = [];

    public function queryTrips(QueryCriteria $criteria, $gibbonPersonID = null, $relation = null, $eutFilter = false) {
        $query = $this
        ->newQuery()
        ->from('tripPlannerRequests')
        ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID = tripPlannerRequests.creatorPersonID')
        ->cols([
        'tripPlannerRequests.tripPlannerRequestID',
        'tripPlannerRequests.creatorPersonID',
        'tripPlannerRequests.timestampCreation',
        'tripPlannerRequests.title as tripTitle',
        'tripPlannerRequests.description',
        'tripPlannerRequests.location',
        'tripPlannerRequests.status',
        'gibbonPerson.title',
        'gibbonPerson.preferredName',
        'gibbonPerson.surname',
        '(SELECT startDate FROM tripPlannerRequestDays WHERE tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID ORDER BY startDate ASC LIMIT 1) as firstDayOfTrip'
        ]);

        if ($eutFilter) {
            $query->where('tripPlannerRequestDays.startDate IS NULL')
                ->where("tripPlannerRequests.status != 'Approved'");
        }

        if (!empty($relation) && !empty($gibbonPersonID)) {
            switch ($relation) {
                //My Requests
                case 'MR':
                    $query->where('tripPlannerRequests.creatorPersonID = :personID')
                        ->bindValue('personID', $gibbonPersonID);
                    break;

                //Involved option
                case 'I':
                    $query->innerJoin('tripPlannerRequestPerson', 'tripPlannerRequestPerson.tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID')
                        ->where('tripPlannerRequestPerson.role = :role')
                        ->bindValue('role', 'Teacher')
                        ->where('tripPlannerRequestPerson.gibbonPersonID = :personID')
                        ->bindValue('personID', $gibbonPersonID);
                    break;

                //Awaiting my approval
                case 'AMA':
                    //TODO: Changed to match previous behavior. Until then reverted to checks in php
                    //$query->where('EXISTS (SELECT tripPlannerApprovers.gibbonPersonID FROM tripPlannerApprovers WHERE tripPlannerApprovers.gibbonPersonID = :personID)')
                    //    ->bindValue('personID', $gibbonPersonID);
                    break;

                default:
                    //Department Requests
                    if (substr($relation, 0, 2) == "DR") {
                        $query->innerJoin('gibbonDepartmentStaff', 'gibbonDepartmentStaff.gibbonPersonID = tripPlannerRequests.creatorPersonID')
                            ->where('gibbonDepartmentStaff.gibbonDepartmentID = :departmentID')
                            ->bindValue('departmentID', substr($relation, 2));
                    }
                    break;
            }
        }

        $criteria->addFilterRules([
            'status' => function($query, $status) {
                return $query->where('tripPlannerRequests.status = :status')
                    ->bindValue('status', $status);
            },
            'statuses' => function ($query, $statuses) {
                //Whilst, according to https://github.com/auraphp/Aura.SqlQuery/blob/513747a1b399b910f6050e78bd64c3c125a81abf/docs/select.md 
                //it should be possible to use ->where(<column> IN (:var), ['var' => ['1','2'...]) this caused issues when I tried
                //Additionally I couldn't pass the array directly in through the criteria filterBy property
                //This has been reported to Aura in https://github.com/auraphp/Aura.SqlQuery/issues/161#issuecomment-713123581

                $statuses = unserialize($statuses);

                if (!is_array($statuses)) {
                    $statuses = array($statuses);
                }

                $inClause = '';
                foreach ($statuses as $key => $status) {
                    $bind = 'status' . $key;
                    $inClause .= ($key > 0 ? ',' : '') . ':' . $bind;
                    $query->bindValue($bind, $status);
                }

                return $query->where('tripPlannerRequests.status IN (' . $inClause . ')'); 
            },
            'year' => function ($query, $gibbonSchoolYearID) {
                return $query->where('tripPlannerRequests.gibbonSchoolYearID = :gibbonSchoolYearID')
                ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID);
            },
            'tripDay' => function($query, $queryDate) {
                return $query->innerJoin('tripPlannerRequestDays','tripPlannerRequestDays.tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID')
                    ->where('tripPlannerRequestDays.startDate <= :queryDate')
                    ->where('tripPlannerRequestDays.endDate >= :queryDate')
                    ->bindValue('queryDate',$queryDate);
            },
        ]);
        return $this->runQuery($query, $criteria);
    }
}
