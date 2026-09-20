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
 * Oculta las trazas (coordinaciones, consultas, documentos, bitácora, chat…)
 * de un caso eliminado lógicamente.
 *
 * El `NOT EXISTS` resuelve por clave primaria de `telemedicine_cases`, así que
 * añade una búsqueda por índice y no un join que multiplique filas. Las filas
 * sin caso vinculado (`telemedicine_case_id` nulo) nunca se ocultan.
 *
 * La conexión se toma del modelo y nunca del builder: `getConnection()` no
 * existe en el builder de Eloquent y `__call` lo reenvía por `toBase()`, que
 * vuelve a aplicar los scopes y deja el proceso en recursión infinita.
 */
final class HideDeletedTelemedicineCaseTracesScope implements Scope
{
    public function __construct(private readonly string $column = 'telemedicine_case_id') {}

    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! TelemedicineCaseTablePresence::has($model->getConnection(), 'telemedicine_cases')) {
            return;
        }

        $qualifiedColumn = $model->qualifyColumn($this->column);

        $builder->whereNotExists(function (QueryBuilder $query) use ($qualifiedColumn): void {
            $query->select(DB::raw(1))
                ->from('telemedicine_cases')
                ->whereColumn('telemedicine_cases.id', $qualifiedColumn)
                ->where('telemedicine_cases.status', TelemedicineCaseDeletion::STATUS);
        });
    }
}
