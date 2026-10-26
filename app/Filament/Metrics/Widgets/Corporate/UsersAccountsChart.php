<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use Illuminate\Contracts\Support\Htmlable;

class UsersAccountsChart extends CorporateDonutChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'Cuentas del sistema';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Cuentas activas a hoy, por tipo de usuario.';
    }

    protected function values(): array
    {
        return CorporateUsersMetrics::accountsByType();
    }

    public function noun(): string
    {
        return 'cuentas';
    }

    protected function colorRoles(): array
    {
        return [
            'Internos' => 'series1',
            'Médicos' => 'good',
            'Agentes' => 'violet',
            'Agencias' => 'warning',
            'Proveedores' => 'muted',
        ];
    }
}
