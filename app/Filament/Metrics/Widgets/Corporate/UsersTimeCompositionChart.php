<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;
use Illuminate\Contracts\Support\Htmlable;

class UsersTimeCompositionChart extends CorporateDonutChart
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    public function getHeading(): string|Htmlable|null
    {
        return 'En qué se va el tiempo conectado';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Horas del personal con el sistema abierto: usándolo, sin tocarlo por más de 5 min o en otra pestaña.';
    }

    protected function values(): array
    {
        return CorporateUsersMetrics::timeComposition($this->period());
    }

    public function noun(): string
    {
        return 'horas';
    }

    public function formatValue(int|float $value): string
    {
        return number_format((float) $value, 0, ',', '.').' h';
    }

    protected function colorRoles(): array
    {
        return [
            'Activo' => 'good',
            'Inactivo' => 'warning',
            'En otra pestaña' => 'muted',
        ];
    }
}
