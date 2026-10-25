<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateFinanceMetrics;

class ReceivablesByAgencyRanking extends CorporateRanking
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    public function getHeading(): string
    {
        return 'Por cobrar por agencia';
    }

    public function getDescription(): string
    {
        return 'Foto a hoy: cuotas pendientes por agencia. Clic para verlas.';
    }

    protected function dimension(): string
    {
        return 'receivable_agency';
    }

    protected function icon(): string
    {
        return 'heroicon-o-clock';
    }

    protected function emptyText(): string
    {
        return 'No hay cuotas pendientes.';
    }

    protected function ranking(): array
    {
        return CorporateFinanceMetrics::receivablesByAgency(8);
    }
}
