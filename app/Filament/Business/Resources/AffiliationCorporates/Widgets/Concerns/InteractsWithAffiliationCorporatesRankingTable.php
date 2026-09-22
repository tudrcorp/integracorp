<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Widgets\Concerns;

use Carbon\Carbon;

trait InteractsWithAffiliationCorporatesRankingTable
{
    /** 0 = todos los años. */
    public string $filterYear = '0';

    /** 0 = todos los meses. */
    public string $filterMonth = '0';

    abstract protected function rankingTableVariant(): string;

    public function getRankingTableVariant(): string
    {
        return $this->rankingTableVariant();
    }

    public function bootInteractsWithAffiliationCorporatesRankingTable(): void
    {
        if ($this->filterYear === '') {
            $this->filterYear = '0';
        }

        if ($this->filterMonth === '') {
            $this->filterMonth = '0';
        }
    }

    /**
     * @return array<string, string>
     */
    public function getRankingYearFilterOptions(): array
    {
        $nowYear = (int) Carbon::now()->year;
        $options = [
            '0' => 'Año (Todos)',
        ];

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
        $year = $this->resolvedRankingFilterYear() ?? (int) Carbon::now()->year;
        $options = ['0' => 'Mes (Todos)'];

        for ($month = 1; $month <= 12; $month++) {
            $label = Carbon::createFromDate($year, $month, 1)
                ->locale($locale)
                ->translatedFormat('F');
            $options[(string) $month] = ucfirst($label);
        }

        return $options;
    }

    public function rankingPeriodLabel(): string
    {
        $year = $this->resolvedRankingFilterYear();
        $month = $this->resolvedRankingFilterMonth();

        if ($year === null && $month === null) {
            return 'Todos';
        }

        if ($year === null) {
            return $this->rankingMonthLabel((int) Carbon::now()->year, $month);
        }

        if ($month === null) {
            return (string) $year;
        }

        return $year.' · '.$this->rankingMonthLabel($year, $month);
    }

    public function normalizedFilterYear(): string
    {
        return $this->filterYear !== '' ? $this->filterYear : '0';
    }

    public function normalizedFilterMonth(): string
    {
        return $this->filterMonth !== '' ? $this->filterMonth : '0';
    }

    protected function resolvedRankingFilterYear(): ?int
    {
        $year = (int) $this->filterYear;

        return $year > 0 ? $year : null;
    }

    protected function resolvedRankingFilterMonth(): ?int
    {
        $month = (int) $this->filterMonth;

        return ($month >= 1 && $month <= 12) ? $month : null;
    }

    protected function rankingMonthLabel(int $year, int $month): string
    {
        return ucfirst(
            Carbon::createFromDate($year, $month, 1)
                ->locale(app()->getLocale())
                ->translatedFormat('F')
        );
    }
}
