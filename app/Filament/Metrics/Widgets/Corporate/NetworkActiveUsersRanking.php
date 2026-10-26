<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;

class NetworkActiveUsersRanking extends UsersRanking
{
    public function getHeading(): string
    {
        return 'Red comercial: más activos';
    }

    public function getDescription(): string
    {
        return 'Agentes, agencias y proveedores con más horas activas en sus portales. Clic para ver sus días.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-briefcase';
    }

    protected function emptyText(): string
    {
        return 'La red comercial no tuvo actividad en este período.';
    }

    protected function ranking(): array
    {
        return CorporateUsersMetrics::topActive($this->period(), CorporateUsersMetrics::NETWORK);
    }
}
