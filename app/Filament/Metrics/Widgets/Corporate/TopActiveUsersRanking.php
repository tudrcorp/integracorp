<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;

class TopActiveUsersRanking extends UsersRanking
{
    public function getHeading(): string
    {
        return 'Top 10 de uso del sistema';
    }

    public function getDescription(): string
    {
        return 'Personal con más horas activas en el período. Clic para ver sus días.';
    }

    protected function ranking(): array
    {
        return CorporateUsersMetrics::topActive($this->period());
    }
}
