<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\Scopes;

use App\Support\Telemedicine\TelemedicineCaseDeletion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Oculta los casos eliminados lógicamente en toda consulta a `telemedicine_cases`.
 *
 * Se aplica como scope global —y no filtrando panel por panel— porque el caso
 * se consulta desde más de cien archivos: paneles, widgets, jobs, PDFs y
 * exportaciones. Quien necesite verlos (la pestaña ELIMINADOS o la restauración)
 * pide explícitamente `withoutGlobalScope(self::class)`.
 */
final class HideDeletedTelemedicineCasesScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(
            $model->qualifyColumn('status'),
            '!=',
            TelemedicineCaseDeletion::STATUS,
        );
    }
}
