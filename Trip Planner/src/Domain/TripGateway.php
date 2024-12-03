<?php

namespace Gibbon\Module\TripPlanner\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Trip Gateway
 */
class TripGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tripPlannerRequests';
    private static $primaryKey = 'tripPlannerRequestID';
    private static $searchableColumns = [];

    public function queryTrips(QueryCriteria $criteria, $gibbonSchoolYearID, $gibbonPersonID = null, $gibbonDepartmentID = null, $expiredUnapproved = null) {
        $query = $this
        ->newQuery()
        ->from('tripPlannerRequests')
        ->innerJoin('gibbonPerson', 'gibbonPerson.gibbonPersonID = tripPlannerRequests.creatorPersonID')
        ->cols([
        'tripPlannerRequests.tripPlannerRequestID',
        'tripPlannerRequests.creatorPersonID',
        'tripPlannerRequests.title as tripTitle',
        'tripPlannerRequests.description',
        'tripPlannerRequests.location',
        'tripPlannerRequests.status',
        'tripPlannerRequests.messengerGroupID',
        'gibbonPerson.title',
        'gibbonPerson.preferredName',
        'gibbonPerson.surname',
        '(SELECT startDate FROM tripPlannerRequestDays WHERE tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID ORDER BY startDate ASC LIMIT 1) as firstDayOfTrip',
        ])
        ->leftJoin('tripPlannerRequestPerson', "tripPlannerRequestPerson.tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID AND tripPlannerRequestPerson.role='Teacher' AND tripPlannerRequestPerson.gibbonPersonID = :gibbonPersonID")
        ->where('gibbonSchoolYearID=:gibbonSchoolYearID')
        ->bindValue('gibbonSchoolYearID', $gibbonSchoolYearID)
        ->bindValue('gibbonPersonID', $gibbonPersonID);

        if ($expiredUnapproved) {
            $query->where("NOT (
                (SELECT IFNULL(MAX(endDate),CURRENT_DATE) FROM tripPlannerRequestDays WHERE tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID) < CURRENT_DATE 
                AND (tripPlannerRequests.status = 'Requested' OR tripPlannerRequests.status = 'Awaiting Final Approval')
                )");
        }

        // A user has been specified, so Filter only my requests and involved trips for this user
        if (!empty($gibbonPersonID)) {
            $query->where('(tripPlannerRequests.creatorPersonID = :gibbonPersonID OR tripPlannerRequestPerson.tripPlannerRequestPersonID IS NOT NULL)');
        }

        if (!empty($gibbonDepartmentID)) {
            $query->innerJoin('gibbonDepartmentStaff', 'gibbonDepartmentStaff.gibbonPersonID = tripPlannerRequests.creatorPersonID')
                ->where('gibbonDepartmentStaff.gibbonDepartmentID = :departmentID')
                ->bindValue('departmentID', $gibbonDepartmentID);
        }

        $criteria->addFilterRules([
            'status' => function($query, $status) {
                return $query->where('tripPlannerRequests.status = :status')
                    ->bindValue('status', $status);
            },
            'showActive' => function($query, $expiredUnapproved) {
                if ($expiredUnapproved == 'Y' ) {
                    $query->where("NOT (
                        (SELECT IFNULL(MAX(endDate),CURRENT_DATE) FROM tripPlannerRequestDays WHERE tripPlannerRequestID = tripPlannerRequests.tripPlannerRequestID) < CURRENT_DATE 
                        AND (tripPlannerRequests.status = 'Cancelled' OR tripPlannerRequests.status = 'Rejected')
                        )");
                }
                return $query;
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

    public function beginTransaction() {
        $this->db()->beginTransaction();
    }

    public function commit() {
        $this->db()->commit();
    }

    public function rollBack() {
        $this->db()->rollBack();
    }
}
