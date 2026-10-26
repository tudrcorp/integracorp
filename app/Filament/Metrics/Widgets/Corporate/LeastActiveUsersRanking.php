<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;

class LeastActiveUsersRanking extends UsersRanking
{
    public function getHeading(): string
    {
        return 'Menor uso del sistema';
    }

    public function getDescription(): string
    {
        return 'Personal con cuenta activa y menos horas activas, incluso sin conexión. Clic para ver sus días.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-arrow-trending-down';
    }

    protected function emptyText(): string
    {
        return 'No hay personal con cuenta activa.';
    }

    protected function ranking(): array
    {
        return CorporateUsersMetrics::leastActive($this->period());
    }
}
