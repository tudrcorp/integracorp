<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporateQuoteEffectiveness;

class AgentQuoteEffectivenessRanking extends QuoteEffectivenessRanking
{
    protected function dimension(): string
    {
        return CorporateQuoteEffectiveness::AGENT;
    }

    protected function heading(): string
    {
        return 'Top 10 · efectividad de agentes';
    }

    protected function sellerNoun(): string
    {
        return 'agentes';
    }
}
