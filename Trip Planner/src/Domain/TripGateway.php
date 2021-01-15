<?php

namespace Gibbon\Module\TripPlanner\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

class TripGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tripPlannerRequests';
    private static $primaryKey = 'gibbonTripPlannerID';
    private static $searchableColumns = [];

    public function queryTrips(QueryCriteria $criteria)
    {
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
        'tripPlannerRequests.date',
        'tripPlannerRequests.startTime',
        'tripPlannerRequests.endTime',
        'tripPlannerRequests.status',
        'tripPlannerRequests.endDate',
        'gibbonPerson.title',
        'gibbonPerson.preferredName',
        'gibbonPerson.surname',
        '(SELECT startDate FROM tripPlannerRequestDays WHERE tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID ORDER BY startDate ASC LIMIT 1) as firstDayOfTrip'
        ]);

        $criteria->addFilterRules([
          'status' => function ($query, $statuses) {
            //Whilst, according to https://github.com/auraphp/Aura.SqlQuery/blob/513747a1b399b910f6050e78bd64c3c125a81abf/docs/select.md 
            //it should be possible to use ->where(<column> IN (:var), ['var' => ['1','2'...]) this caused issues when I tried
            //Additionally I couldn't pass the array directly in through the criteria filterBy property
            //This has been reported to Aura in https://github.com/auraphp/Aura.SqlQuery/issues/161#issuecomment-713123581
            $statuses = unserialize($statuses);
            $bindVals = [];
            $inClause = "";
            foreach($statuses as $key => $status)
            {
              $bindVals["status".$key] = $status;
              $inClause .= ":status".$key;
              if($key != count($statuses)-1)
              {
                $inClause .= ",";
              }
              $query->bindValue("status".$key,$status);
            }
            return $query
              ->where("tripPlannerRequests.status IN (".$inClause.")"); 
        },
        'eutfilter' => function ($query, $eutfilter) use ($criteria) {
          //expiredUnapprovedFilter, set in the module settings
            $query
            ->where('tripPlannerRequestDays.startDate IS NULL')
            ->where("tripPlannerRequests.status != 'Approved'");
        },

        'schoolYearID' => function ($query, $gibbonYearGroupID) {
            return $query
            ->where('tripPlannerRequests.gibbonYearGroupID = :gibbonYearGroupID')
            ->bindValue('gibbonYearGroupID', $gibbonYearGroupID);
        },
        'relation' => function ($query, $relation) {
            $relarr = explode(':', $relation);
            switch ($relation[0]) {
                case 'MR':
                  //My requests option
                  //Only show requests owned by
                    return $query
                ->where('tripPlannerRequests.creatorPersonID = :personID')
                ->bindValue('personID', $relation[1]);

                case 'I':
                  //Involved option
                    return $query
                ->innerJoin('tripPlannerRequestPerson', 'tripPlannerRequestPerson.tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID')
                ->where("tripPlannerRequestPerson.role = 'Teacher'")
                ->where("(tripPlannerRequestPerson.gibbonPersonID = :personID OR tripPlannerRequests.teacherPersonIDs LIKE CONCAT('%',:personID,'%'))")
                ->bindValues($relation[1]);

                case 'AMA':
                  //Awaiting my approval
                    return $query
                ->where('EXISTS (SELECT tripPlannerApprovers.gibbonPersonID FROM tripPlannerApprovers WHERE tripPlannerApprovers.gibbonPersonID = :personID)')
                ->bindValue('personID', $relation[1]);

                default:
                    if (substr($relation[0], 0, 2) == "DR") {
                      //Department Requests
                        return $query
                        ->innerJoin('gibbonDepartmentStaff', 'gibbonDepartmentStaff.gibbonPersonID = tripPlannerRequests.creatorPersonID')
                        ->where('gibbonDepartmentStaff.gibbonDepartmentID = :departmentID')
                        ->bindValue('departmentID', substr($relation[0], 2));
                    }
                  //Don't filter requests, do nothing
                    return $query;
            }
        },
        'tripDay' => function($query,$queryDate) {
          return $query
            ->innerJoin('tripPlannerRequestDays','tripPlannerRequestDays.tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID')
            ->where('tripPlannerRequestDays.startDate <= :queryDate')
            ->where('tripPlannerRequestDays.endDate >= :queryDate')
            ->bindValue('queryDate',$queryDate);
        }
        ]);
        return $this->runQuery($query, $criteria);
    }
}
