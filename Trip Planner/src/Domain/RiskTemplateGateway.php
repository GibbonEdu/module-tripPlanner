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
class RiskTemplateGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'tripPlannerRiskTemplates';
    private static $primaryKey = 'tripPlannerRiskTemplateID';
    private static $searchableColumns = [];

    public function queryTemplates($critera) {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'tripPlannerRiskTemplateID', 'name', 'body'
            ]);

        return $this->runQuery($query, $critera);
    }

}
