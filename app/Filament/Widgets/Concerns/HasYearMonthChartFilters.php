<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Support\Carbon;

/**
 * Dos desplegables (año y mes) para períodos en gráficos Filament.
 * Si el año es el actual, el mes solo incluye meses ya transcurridos.
 *
 * Requiere {@see \Filament\Widgets\ChartWidget} (propiedad `cachedData`, método `updateChartData()`).
 */
trait HasYearMonthChartFilters
{
    public ?string $filterYear = null;

    /**
     * Mes seleccionado:
     * - `1..12`: mes específico
     * - `0`: todo el año
     */
    public ?int $filterMonth = null;

    /**
     * Valores por defecto: mes y año calendario actuales.
     */
    protected function applyDefaultYearMonthForMount(): void
    {
        $now = now();
        if ($this->filterYear === null || $this->filterYear === '') {
            $this->filterYear = (string) $now->year;
        }
        if ($this->filterMonth === null) {
            $this->filterMonth = (int) $now->month;
        }
        $this->clampMonthToSelectedYear();
    }

    /**
     * Valores por defecto: mes calendario anterior (útil en comparativos “mes actual vs mes seleccionado”).
     */
    protected function applyDefaultYearMonthForMountPreviousMonth(): void
    {
        $ref = now()->copy()->subMonth();
        if ($this->filterYear === null || $this->filterYear === '') {
            $this->filterYear = (string) $ref->year;
        }
        if ($this->filterMonth === null) {
            $this->filterMonth = (int) $ref->month;
        }
        $this->clampMonthToSelectedYear();
    }

    /**
     * @return array<string, string>
     */
    public function getChartYearOptions(): array
    {
        $current = (int) now()->year;
        $options = [];
        for ($i = 0; $i < 12; $i++) {
            $y = (string) ($current - $i);
            $options[$y] = $y;
        }

        return $options;
    }

    /**
     * @return array<string, string> clave "0" (todo el año) o "1"–"12" (mes)
     */
    public function getChartMonthOptions(): array
    {
        $year = (int) ($this->filterYear ?? now()->year);
        $now = now();
        $maxMonth = 12;
        if ($year === (int) $now->year) {
            $maxMonth = (int) $now->month;
        }

        $options = [
            '0' => 'Todo el año',
        ];
        for ($m = 1; $m <= $maxMonth; $m++) {
            $key = (string) $m;
            $options[$key] = ucfirst(Carbon::createFromDate($year, $m, 1)->translatedFormat('F'));
        }

        return $options;
    }

    public function updatedFilterYear(): void
    {
        $this->clampMonthToSelectedYear();
        $this->invalidateYearMonthChartCache();
    }

    public function updatedFilterMonth(): void
    {
        $this->invalidateYearMonthChartCache();
    }

    protected function clampMonthToSelectedYear(): void
    {
        $year = (int) ($this->filterYear ?? now()->year);
        $now = now();
        $max = 12;
        if ($year === (int) $now->year) {
            $max = (int) $now->month;
        }
        $m = (int) ($this->filterMonth ?? $max);
        // Permitir 0 ("Todo el año").
        $this->filterMonth = max(0, min($max, $m));
    }

    /**
     * @return array{0: int, 1: ?int} [año, mes 1–12 | null si todo el año]
     */
    protected function resolveSelectedYearMonth(): array
    {
        $this->clampMonthToSelectedYear();
        $year = (int) ($this->filterYear ?? now()->year);
        $month = (int) ($this->filterMonth ?? now()->month);

        if ($month === 0) {
            return [$year, null];
        }

        return [$year, max(1, min(12, $month))];
    }

    private function invalidateYearMonthChartCache(): void
    {
        $this->cachedData = null;
        $this->updateChartData();
    }
}
