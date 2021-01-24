<?php

namespace Gibbon\Module\TripPlanner\Domain\Traits;

use Gibbon\Domain\Traits\TableAware;

/**
 * Provides method for Trip Planner Request Gateways to bulk insert data by Trip Planner Request ID.
 * Note this is an extension of TableAware
 */
trait BulkInsert
{

    use TableAware;

    public function bulkInsert($tripPlannerRequestID, $data) {
        if (empty($data)) {
            return;
        }

        $query = $this
            ->newInsert()
            ->into($this->getTableName());

        foreach ($data as $row) {
            $query->addRow($row);
            $query->set('tripPlannerRequestID', $tripPlannerRequestID);
        }

        return $this->runInsert($query);
    }

}

?>