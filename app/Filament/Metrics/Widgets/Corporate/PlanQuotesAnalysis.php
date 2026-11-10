<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Metrics\Corporate\CorporateQuoteAnalysis;
use Filament\Widgets\Widget;

/**
 * Análisis de cotizaciones por plan (Inicial, Ideal, Especial): individuales y corporativas por
 * separado, con su partición (emitidas, en curso, anuladas, eliminadas), tasas y su variación
 * contra el período anterior, y la lectura automática del período.
 */
class PlanQuotesAnalysis extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.metrics.corporate.plan-quotes-analysis';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();

        return [
            'analysis' => CorporateQuoteAnalysis::build($period),
            'periodLabel' => $period->label(),
            'previousLabel' => $period->previousLabel(),
            'periodKey' => $period->cacheKey(),
        ];
    }
}
