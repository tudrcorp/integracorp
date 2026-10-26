<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateUsersMetrics;

class TopProductiveUsersRanking extends UsersRanking
{
    public function getHeading(): string
    {
        return 'Top 10 de productividad';
    }

    public function getDescription(): string
    {
        return 'Registros creados en el período (afiliaciones, cotizaciones, coordinaciones, órdenes y consultas). Clic para ver sus días.';
    }

    protected function icon(): string
    {
        return 'heroicon-o-document-plus';
    }

    protected function noun(): string
    {
        return 'registros';
    }

    protected function decimals(): int
    {
        return 0;
    }

    protected function suffix(): string
    {
        return '';
    }

    protected function emptyText(): string
    {
        return 'El personal no creó registros en este período.';
    }

    protected function ranking(): array
    {
        return CorporateUsersMetrics::topProductive($this->period());
    }
}
