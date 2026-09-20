<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\Scopes;

use App\Support\Telemedicine\TelemedicineCaseDeletion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Oculta las órdenes de servicio de un caso eliminado.
 *
 * La orden no guarda el caso: cuelga de la coordinación (`operation_coordination_service_id`),
 * así que el vínculo con el caso se resuelve a través de ella.
 */
final class HideDeletedTelemedicineCaseServiceOrdersScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $connection = $model->getConnection();

        if (! TelemedicineCaseTablePresence::has($connection, 'telemedicine_cases')
            || ! TelemedicineCaseTablePresence::has($connection, 'operation_coordination_services')) {
            return;
        }

        $qualifiedColumn = $model->qualifyColumn('operation_coordination_service_id');

        $builder->whereNotExists(function (QueryBuilder $query) use ($qualifiedColumn): void {
            $query->select(DB::raw(1))
                ->from('operation_coordination_services')
                ->join(
                    'telemedicine_cases',
                    'telemedicine_cases.id',
                    '=',
                    'operation_coordination_services.telemedicine_case_id',
                )
                ->whereColumn('operation_coordination_services.id', $qualifiedColumn)
                ->where('telemedicine_cases.status', TelemedicineCaseDeletion::STATUS);
        });
    }
}
