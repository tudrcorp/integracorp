<?php

namespace App\Filament\Widgets\Concerns;

trait IosLiquidGlassBarChartWidget
{
    public function iosBarChartHasData(): bool
    {
        $data = $this->getCachedData();

        foreach ($data['datasets'] ?? [] as $dataset) {
            foreach ($dataset['data'] ?? [] as $value) {
                if ((float) $value > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getIosBarChartWireKey(): string
    {
        if (property_exists($this, 'filterYear') && property_exists($this, 'filterMonth')) {
            $suffix = ((string) ($this->filterYear ?? '')).'-'.((string) ($this->filterMonth ?? ''));
        } elseif (property_exists($this, 'filter')) {
            $suffix = (string) ($this->filter ?? 'default');
        } else {
            $suffix = 'default';
        }

        return 'ios-bar-'.str_replace('\\', '-', static::class).'-'.$suffix;
    }

    public function updatedFilter(?string $value): void
    {
        $this->cachedData = null;
    }

    public function getIosBarChartEmptyTitle(): string
    {
        return 'Sin datos en este periodo';
    }

    public function getIosBarChartEmptyBody(): string
    {
        return 'No hay información para mostrar con los filtros seleccionados.';
    }
}
