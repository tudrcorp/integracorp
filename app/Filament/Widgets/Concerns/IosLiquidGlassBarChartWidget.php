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
        $suffix = property_exists($this, 'filter') ? (string) ($this->filter ?? 'default') : 'default';

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
