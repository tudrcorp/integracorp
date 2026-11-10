<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Metrics\Corporate\CorporateQuoteEffectiveness;
use App\Support\Metrics\Corporate\CorporateQuoteMetrics;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * Top 10 de efectividad de la fuerza de ventas: quién cotiza y cuánto de eso se convierte en
 * afiliación, en tres vistas (mejor efectividad, más convierten, más cotizan).
 */
abstract class QuoteEffectivenessRanking extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.metrics.corporate.quote-effectiveness-ranking';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    abstract protected function dimension(): string;

    abstract protected function heading(): string;

    abstract protected function sellerNoun(): string;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $data = CorporateQuoteEffectiveness::build($period, $this->dimension());

        return [
            'heading' => $this->heading(),
            'sellerNoun' => $this->sellerNoun(),
            'dimension' => $this->dimension(),
            'rankings' => CorporateQuoteEffectiveness::rankings($data['rows']),
            'network' => $data['network'],
            'withoutAgent' => $data['without_agent'],
            'minQuotes' => CorporateQuoteEffectiveness::MIN_QUOTES,
            'compare' => $period->compare,
            'previousLabel' => $period->previousLabel(),
            'periodLabel' => $period->label(),
            'cohortOpen' => $period->to->greaterThanOrEqualTo(CarbonImmutable::today()->subDays(CorporateQuoteMetrics::AUTO_ANULATION_DAYS)),
            'periodKey' => $period->cacheKey(),
        ];
    }
}
