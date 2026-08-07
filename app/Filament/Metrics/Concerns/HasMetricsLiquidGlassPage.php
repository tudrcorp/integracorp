<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Concerns;

use Illuminate\Contracts\Support\Htmlable;

trait HasMetricsLiquidGlassPage
{
    public function getView(): string
    {
        return 'filament.metrics.pages.module-shell';
    }

    abstract public static function metricsModuleKey(): string;

    abstract public static function metricsModuleTitle(): string;

    abstract public static function metricsModuleSubtitle(): string;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array{key: string, title: string, subtitle: string, eyebrow: string}
     */
    public function getMetricsShellData(): array
    {
        return [
            'key' => static::metricsModuleKey(),
            'title' => static::metricsModuleTitle(),
            'subtitle' => static::metricsModuleSubtitle(),
            'eyebrow' => 'Métricas / KPI',
        ];
    }
}
