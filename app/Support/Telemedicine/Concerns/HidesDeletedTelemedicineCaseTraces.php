<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\Concerns;

use App\Support\Telemedicine\Scopes\HideDeletedTelemedicineCaseTracesScope;

/**
 * Da de alta el scope global que oculta las filas de un caso eliminado.
 *
 * Un modelo cuya columna del caso no sea `telemedicine_case_id` sobreescribe
 * `telemedicineCaseColumn()`.
 */
trait HidesDeletedTelemedicineCaseTraces
{
    public static function bootHidesDeletedTelemedicineCaseTraces(): void
    {
        static::addGlobalScope(new HideDeletedTelemedicineCaseTracesScope(static::telemedicineCaseColumn()));
    }

    protected static function telemedicineCaseColumn(): string
    {
        return 'telemedicine_case_id';
    }
}
