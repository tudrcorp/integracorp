<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateRanking;
use App\Support\Business\Dashboard\BusinessDashboardMetrics;

/**
 * Producción del equipo de Negocios en el período.
 */
class BusinessTeamRanking extends CorporateRanking
{
    public function getHeading(): string
    {
        return 'Nuevas ventas del equipo';
    }

    public function getDescription(): string
    {
        return 'Afiliaciones nuevas creadas por cada analista de Negocios, con sus cotizaciones y el dinero nuevo que generaron. Las renovaciones no suman.';
    }

    protected function dimension(): string
    {
        return '';
    }

    protected function icon(): string
    {
        return 'heroicon-o-user-group';
    }

    protected function isMoney(): bool
    {
        return false;
    }

    protected function noun(): string
    {
        return 'afiliaciones';
    }

    protected function ranking(): array
    {
        return BusinessDashboardMetrics::teamRanking($this->period());
    }
}
