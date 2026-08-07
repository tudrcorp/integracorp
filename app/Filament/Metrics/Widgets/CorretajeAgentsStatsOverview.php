<?php

declare(strict_types=1);

namespace App\Filament\Metrics\Widgets;

use App\Services\IntegracorpApi\CorretajeAgentsMetricsClient;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Log;
use Throwable;

class CorretajeAgentsStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    /**
     * KPIs en el primer paint (el gráfico se carga lazy por separado).
     */
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
                label: 'TOTAL REGISTRADOS',
                value: $metrics['total_registered'],
                description: 'Agentes de corretaje en el sistema',
                icon: 'heroicon-m-user-group',
                tone: 'teal',
            ),
            $this->makeStat(
                label: 'TOTAL ACTIVOS',
                value: $metrics['total_active'],
                description: 'Estatus ACTIVO',
                icon: 'heroicon-m-check-badge',
                tone: 'emerald',
            ),
            $this->makeStat(
                label: 'AGENTES SUPERIORES',
                value: $metrics['total_superiors'],
                description: 'Tipo agente (superiores)',
                icon: 'heroicon-m-star',
                tone: 'sky',
            ),
            $this->makeStat(
                label: 'SUBAGENTES',
                value: $metrics['total_subagents'],
                description: 'Tipo sub-agente',
                icon: 'heroicon-m-users',
                tone: 'violet',
            ),
        ];
    }

    /**
     * @return array{
     *     total_registered: int,
     *     total_active: int,
     *     total_superiors: int,
     *     total_subagents: int
     * }
     */
    private function resolveMetrics(): array
    {
        try {
            return app(CorretajeAgentsMetricsClient::class)->summary();
        } catch (Throwable $exception) {
            Log::warning('No se pudieron cargar métricas de agentes de corretaje desde integracorp-api.', [
                'message' => $exception->getMessage(),
            ]);

            return [
                'total_registered' => 0,
                'total_active' => 0,
                'total_superiors' => 0,
                'total_subagents' => 0,
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
