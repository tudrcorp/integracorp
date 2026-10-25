<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Metrics\Corporate\CorporateCommercialMetrics;
use Filament\Widgets\Widget;

/**
 * Embudo de las cotizaciones individuales del período: cuántas avanzan y
 * cuántas se convierten, con el paso entre etapas.
 */
class CorporateFunnel extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.metrics.corporate.funnel';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $steps = CorporateCommercialMetrics::funnel($period);
        $top = max(1, $steps[0]['value'] ?? 0);
        $previous = null;

        foreach ($steps as $index => $step) {
            $steps[$index]['width'] = $step['value'] > 0 ? max(4, round($step['value'] * 100 / $top, 1)) : 0;
            $steps[$index]['from_previous'] = $previous !== null && $previous > 0
                ? number_format($step['value'] * 100 / $previous, 1, ',', '.').' % de la etapa anterior'
                : null;
            $previous = $step['value'];
        }

        return [
            'steps' => $steps,
            'periodKey' => $period->cacheKey(),
        ];
    }
}
