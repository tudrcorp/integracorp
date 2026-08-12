<?php

namespace App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\Concerns;

use Carbon\Carbon;

trait InteractsWithCorporateQuoteRequestsRankingTable
{
    public string $filterYear = '';

    /** 0 = todos los meses del año. */
    public string $filterMonth = '0';

    abstract protected function rankingTableVariant(): string;

    public function getRankingTableVariant(): string
    {
        return $this->rankingTableVariant();
    }

    public function bootInteractsWithCorporateQuoteRequestsRankingTable(): void
    {
        $this->filterYear = $this->filterYear !== ''
            ? $this->filterYear
            : (string) Carbon::now()->year;
        $this->filterMonth = $this->filterMonth !== ''
            ? $this->filterMonth
            : '0';
    }

    /**
     * @return array<string, string>
     */
    public function getRankingYearFilterOptions(): array
    {
        $nowYear = (int) Carbon::now()->year;
        $options = [];

        for ($i = 0; $i < 4; $i++) {
            $year = $nowYear - $i;
            $options[(string) $year] = 'Año '.$year;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function getRankingMonthFilterOptions(): array
    {
        $locale = app()->getLocale();
        $year = $this->resolvedRankingFilterYear();
        $options = ['0' => 'Mes (Todos)'];

        for ($month = 1; $month <= 12; $month++) {
            $label = Carbon::createFromDate($year, $month, 1)
                ->locale($locale)
                ->translatedFormat('F');
            $options[(string) $month] = ucfirst($label);
        }

        return $options;
    }

    protected function resolvedRankingFilterYear(): int
    {
        $year = (int) $this->filterYear;

        return $year > 0 ? $year : (int) Carbon::now()->year;
    }

    protected function resolvedRankingFilterMonth(): ?int
    {
        $month = (int) $this->filterMonth;

        return ($month >= 1 && $month <= 12) ? $month : null;
    }
}
