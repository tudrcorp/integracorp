<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Services\IntegracorpApi\CorretajeAgenciesMetricsClient;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Log;
use Throwable;

class CorretajeAgenciesStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.stats-overview-agent-ios';

    protected ?string $heading = null;

    protected ?string $description = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, int>
     */
    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'sm' => 2,
            'xl' => 4,
        ];
    }

    protected function getStats(): array
    {
        $metrics = $this->resolveMetrics();

        return [
            $this->makeStat(
                label: 'TOTAL REGISTRADAS',
                value: $metrics['total_registered'],
                description: 'Agencias de corretaje en el sistema',
                icon: 'heroicon-m-building-storefront',
                tone: 'teal',
            ),
            $this->makeStat(
                label: 'TOTAL ACTIVAS',
                value: $metrics['total_active'],
                description: 'Estatus ACTIVO',
                icon: 'heroicon-m-check-badge',
                tone: 'emerald',
            ),
            $this->makeStat(
                label: 'AGENCIAS MASTER',
                value: $metrics['total_masters'],
                description: 'Tipo MASTER',
                icon: 'heroicon-m-star',
                tone: 'sky',
            ),
            $this->makeStat(
                label: 'AGENCIAS GENERAL',
                value: $metrics['total_generals'],
                description: 'Tipo GENERAL',
                icon: 'heroicon-m-building-office-2',
                tone: 'violet',
            ),
        ];
    }

    /**
     * @return array{
     *     total_registered: int,
     *     total_active: int,
     *     total_masters: int,
     *     total_generals: int
     * }
     */
    private function resolveMetrics(): array
    {
        try {
            return app(CorretajeAgenciesMetricsClient::class)->summary();
        } catch (Throwable $exception) {
            Log::warning('No se pudieron cargar métricas de agencias de corretaje desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'total_registered' => 0,
                'total_active' => 0,
                'total_masters' => 0,
                'total_generals' => 0,
            ];
        }
    }

    private function makeStat(
        string $label,
        int $value,
        string $description,
        string $icon,
        string $tone,
    ): Stat {
        return Stat::make($label, number_format($value, 0, ',', '.'))
            ->description($description)
            ->descriptionIcon($icon)
            ->color('primary')
            ->extraAttributes([
                'class' => "fi-metrics-corretaje-agent-stat fi-metrics-corretaje-agent-stat--{$tone}",
            ]);
    }
}
