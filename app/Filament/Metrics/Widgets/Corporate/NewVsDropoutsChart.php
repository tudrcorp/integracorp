<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Corporate;

use App\Support\Metrics\Corporate\CorporatePortfolioMetrics;
use Illuminate\Contracts\Support\Htmlable;

class NewVsDropoutsChart extends CorporateComparisonChart
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Altas frente a bajas';
    }

    protected function intro(): string
    {
        return 'Si las bajas superan a las altas, la cartera se encoge.';
    }

    protected function noun(): string
    {
        return 'afiliaciones';
    }

    protected function series(): array
    {
        $data = CorporatePortfolioMetrics::newVsDropouts($this->period());

        return ['labels' => $data['labels'], 'first' => $data['new'], 'second' => $data['dropouts']];
    }

    protected function names(): array
    {
        return ['Altas', 'Bajas'];
    }

    protected function differenceLabel(): string
    {
        return 'Crecimiento neto';
    }
}
