<?php
namespace Gibbon\Module\TripPlanner\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Services\Format;

/**
 * Technician Gateway
 *
 * @version v20
 * @since   v20
 */
class ApproverGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tripPlannerApprovers';
    private static $primaryKey = 'tripPlannerApproverID';
    private static $searchableColumns = [];

    public function queryApprovers($critera) {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tripPlannerApproverID', 'title', 'preferredName', 'surname', 'sequenceNumber', 'finalApprover'
            ])
            ->leftJoin('gibbonPerson', 'tripPlannerApprovers.gibbonPersonID=gibbonPerson.gibbonPersonID');

        return $this->runQuery($query, $critera);
    }

    public function selectStaffForApprover($ignore = true) {
        $select = $this
            ->newSelect()
            ->from('gibbonPerson')
            ->cols([
                'gibbonPerson.gibbonPersonID', 'title', 'surname', 'preferredName', 'username'
            ])
            ->innerJoin('gibbonStaff', 'gibbonStaff.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->where('gibbonPerson.status=:status')
            ->bindValue('status', 'Full')
            ->orderBy(['surname', 'preferredName']);

        if ($ignore) {
            $select->leftJoin($this->getTableName(), 'tripPlannerApprovers.gibbonPersonID=gibbonPerson.gibbonPersonID')
                ->where('tripPlannerApprovers.gibbonPersonID IS NULL');
        }

        $result = $this->runSelect($select);
        $users = array_reduce($result->fetchAll(), function ($group, $item) {
            $group[$item['gibbonPersonID']] = Format::name($item['title'], $item['preferredName'], $item['surname'], 'Staff', false, true) . ' (' . $item['username'] . ')';
            return $group;
        }, array());

        return $users;
    }

    public function insertApprover($gibbonPersonID, $finalApprover) {
        $select = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['MAX(sequenceNumber) + 1 as sequenceNumber']);
        $result = $this->runSelect($select);

        if ($result->rowCount() > 0) {
            $sequenceNumber = $result->fetch()['sequenceNumber'];
        } else {
            return false;
        }

        $this->insert(['gibbonPersonID' => $gibbonPersonID, 'sequenceNumber' => $sequenceNumber, 'finalApprover' => $finalApprover]);
        return true;
    }

    public function updateSequence($order) {
        $this->db()->beginTransaction();

        for ($count = 0; $count < count($order); $count++) {
            if (!$this->update($order[$count], ['sequenceNumber' => $count])) {
                $this->db()->rollback();
                return false;
            }
        }

        $this->db()->commit();
        return true;
    }

    public function selectNextApprover($tripPlannerRequestID) { 
        $select = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols([
                'gibbonPersonID'
            ])
            ->where('sequenceNumber > (
                SELECT COALESCE(MAX(`tripPlannerApprovers`.`sequenceNumber`), -1)
                FROM `tripplannerrequestlog`
                LEFT JOIN `tripplannerapprovers` ON (`tripplannerapprovers`.`gibbonPersonID` = tripplannerrequestlog.gibbonPersonID)
                WHERE `tripPlannerRequestID` = :tripPlannerRequestID
                AND `action`=\'Approval - Partial\')')
            ->bindValue('tripPlannerRequestID', $tripPlannerRequestID)
            ->orderBy(['sequenceNumber'])
            ->limit(1);

        return $this->runSelect($select);
    }

}
