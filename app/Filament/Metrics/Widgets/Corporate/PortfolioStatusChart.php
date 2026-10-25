<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;
use Illuminate\Contracts\Support\Htmlable;

class PortfolioStatusChart extends CorporateDonutChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Estatus de la cartera';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Foto a hoy de todas las afiliaciones. Clic para ver el listado.';
    }

    protected function values(): array
    {
        return CorporatePortfolioMetrics::byStatus();
    }

    public function noun(): string
    {
        return 'afiliaciones';
    }

    public function detailDimension(): string
    {
        return 'portfolio_status';
    }

    protected function colorRoles(): array
    {
        return [
            CorporatePortfolioMetrics::ACTIVE => 'good',
            'PRE-APROBADA' => 'warning',
            'EXCLUIDO' => 'critical',
            'EXCLUIDA' => 'critical',
            'ANULADA' => 'critical',
            'INACTIVA' => 'muted',
        ];
    }
}
