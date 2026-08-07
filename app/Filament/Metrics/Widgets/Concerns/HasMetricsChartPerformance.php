<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets\Concerns;

use Illuminate\Contracts\View\View;

trait HasMetricsChartPerformance
{
    public function placeholder(): View
    {
        return view('filament.metrics.partials.chart-loading-placeholder', [
            'height' => $this->getPlaceholderHeight() ?? '28rem',
            'heading' => $this->metricsChartPlaceholderHeading(),
            'columnSpan' => $this->getColumnSpan(),
            'columnStart' => $this->getColumnStart(),
        ]);
    }

    protected function metricsChartPlaceholderHeading(): string
    {
        if (property_exists($this, 'heading') && is_string($this->heading) && $this->heading !== '') {
            return $this->heading;
        }

        return 'Cargando gráfico';
    }
}
