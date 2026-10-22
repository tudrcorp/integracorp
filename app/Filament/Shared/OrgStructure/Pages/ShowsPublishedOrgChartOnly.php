<?php

declare(strict_types=1);

namespace App\Filament\Shared\OrgStructure\Pages;

/**
 * Consulta del organigrama publicado. El menú no se registra si no hay vigente.
 */
trait ShowsPublishedOrgChartOnly
{
    protected static function showsPublishedChartOnly(): bool
    {
        return true;
    }
}
