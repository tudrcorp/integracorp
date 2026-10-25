<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateCommercialMetrics;

class SalesByAgentRanking extends CorporateRanking
{
    public function getHeading(): string
    {
        return 'Agentes que más venden';
    }

    public function getDescription(): string
    {
        return 'Ingresos del período por agente. Clic para ver los pagos.';
    }

    protected function dimension(): string
    {
        return 'sale_agent';
    }

    protected function icon(): string
    {
        return 'heroicon-o-user';
    }

    protected function ranking(): array
    {
        return CorporateCommercialMetrics::salesByAgent($this->period(), 8);
    }
}
