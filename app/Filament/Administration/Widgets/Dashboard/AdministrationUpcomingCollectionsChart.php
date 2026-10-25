<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\CorporateBarChart;
use App\Support\Administration\Dashboard\AdministrationFinanceMetrics;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Cobranza esperada por semana: lo que debería entrar en los próximos días.
 */
class AdministrationUpcomingCollectionsChart extends CorporateBarChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Cobranza esperada';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Cuotas pendientes con fecha de pago en los próximos '.AdministrationFinanceMetrics::HORIZON_DAYS.' días, por semana.';
    }

    protected function values(): array
    {
        $data = AdministrationFinanceMetrics::upcomingCollections();

        return array_combine($data['labels'], $data['values']) ?: [];
    }

    protected function isMoney(): bool
    {
        return true;
    }

    protected function shareText(): string
    {
        return ' de lo esperado';
    }
}
