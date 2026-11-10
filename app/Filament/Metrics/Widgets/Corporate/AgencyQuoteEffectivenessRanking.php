<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateQuoteEffectiveness;

class AgencyQuoteEffectivenessRanking extends QuoteEffectivenessRanking
{
    protected function dimension(): string
    {
        return CorporateQuoteEffectiveness::AGENCY;
    }

    protected function heading(): string
    {
        return 'Top 10 · efectividad de agencias';
    }

    protected function sellerNoun(): string
    {
        return 'agencias';
    }
}
