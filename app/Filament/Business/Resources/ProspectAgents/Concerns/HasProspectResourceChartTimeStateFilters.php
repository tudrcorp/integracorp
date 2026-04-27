<?php

namespace App\Filament\Business\Resources\ProspectAgents\Concerns;

use App\Models\State;
use Carbon\Carbon;

trait HasProspectResourceChartTimeStateFilters
{
    public ?string $chartYear = null;

    /** 0 = todo el año (cuando aplica mes opcional). */
    public ?string $chartMonth = null;

    /** Cadena vacía = todos los estados (geográficos). */
    public ?string $chartStateId = null;

    protected function bootProspectChartFilters(): void
    {
        $this->chartYear ??= (string) Carbon::now()->year;
        $this->chartMonth ??= '0';
        $this->chartStateId ??= '';
    }

    /**
     * @return array<string, string>
     */
    public function getChartYearSelectOptions(): array
    {
        $nowYear = (int) Carbon::now()->year;
        $opts = [];
        for ($i = 0; $i < 3; $i++) {
            $y = $nowYear - $i;
            $opts[(string) $y] = (string) $y;
        }

        return $opts;
    }

    /**
     * @return array<string, string>
     */
    public function getChartMonthSelectOptions(): array
    {
        $locale = app()->getLocale();
        $options = ['0' => 'Todo el año'];
        $year = (int) ($this->chartYear ?? Carbon::now()->year);
        for ($m = 1; $m <= 12; $m++) {
            $options[(string) $m] = ucfirst(Carbon::createFromDate($year, $m, 1)->locale($locale)->translatedFormat('F'));
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function getChartStateSelectOptions(): array
    {
        return ['' => 'Todos los estados']
            + State::query()->orderBy('definition')->pluck('definition', 'id')->all();
    }

    protected function resolvedChartYear(): int
    {
        $y = (int) ($this->chartYear ?? Carbon::now()->year);

        return $y > 0 ? $y : (int) Carbon::now()->year;
    }

    protected function resolvedChartMonth(): ?int
    {
        $m = (int) ($this->chartMonth ?? 0);

        return ($m >= 1 && $m <= 12) ? $m : null;
    }

    protected function resolvedChartStateId(): ?int
    {
        $id = $this->chartStateId;

        return ($id !== null && $id !== '') ? (int) $id : null;
    }
}
