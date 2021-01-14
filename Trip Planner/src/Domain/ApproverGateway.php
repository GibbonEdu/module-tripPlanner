<?php
namespace Gibbon\Module\TripPlanner\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

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

}
