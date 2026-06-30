<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Widgets\Concerns;

trait InteractsWithIndividualQuotesRankingTable
{
    abstract protected function rankingTableVariant(): string;

    public function getRankingTableVariant(): string
    {
        return $this->rankingTableVariant();
    }
}
