<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use Filament\Widgets\Widget;

/**
 * Carga operativa: cuántos servicios arrancan cada día de la semana y hora.
 * Rampa secuencial de un solo azul (7 escalones validados en claro y oscuro).
 */
class OperationsWorkloadHeatmap extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 7;

    protected string $view = 'filament.operations.dashboard.heatmap';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 8];

    public const STEPS = 7;

    /**
     * Escalón de la rampa (0 = sin servicios, 1..7 de menos a más).
     */
    public static function step(int $value, int $max): int
    {
        if ($value <= 0 || $max <= 0) {
            return 0;
        }

        return max(1, min(self::STEPS, (int) ceil($value * self::STEPS / $max)));
    }

    /**
     * @return array{matrix: list<list<int>>, max: int, total: int}
     */
    protected function heatmapData(): array
    {
        return OperationsDashboardAnalytics::heatmap($this->period());
    }

    /**
     * Textos del widget. El del proveedor habla de consultas.
     *
     * @return array{heading: string, description: string, noun: string, singular: string, emptyText: string, clickText: string}
     */
    protected function texts(): array
    {
        return [
            'heading' => 'Carga operativa por día y hora',
            'description' => 'Servicios según el día de la semana y la hora de inicio; la escala de abajo va de menos a más. Haga clic en una celda para ver esos servicios.',
            'noun' => 'servicios',
            'singular' => 'servicio',
            'emptyText' => 'No hay servicios con hora de inicio en este período.',
            'clickText' => 'Clic para ver los servicios de este tramo',
        ];
    }

    /**
     * Solo para tests.
     *
     * @return array<string, mixed>
     */
    public function getViewDataForTest(): array
    {
        return $this->getViewData();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $heatmap = $this->heatmapData();

        $busiest = null;

        foreach ($heatmap['matrix'] as $weekday => $hours) {
            foreach ($hours as $hour => $value) {
                if ($value > 0 && ($busiest === null || $value > $busiest['value'])) {
                    $busiest = ['weekday' => $weekday, 'hour' => $hour, 'value' => $value];
                }
            }
        }

        $activeSlots = count(array_filter(array_merge(...$heatmap['matrix'])));
        $byWeekday = array_map('array_sum', $heatmap['matrix']);
        $byHour = array_map(static fn (int $hour): int => array_sum(array_column($heatmap['matrix'], $hour)), range(0, 23));

        return $this->texts() + [
            'matrix' => $heatmap['matrix'],
            'max' => $heatmap['max'],
            'total' => $heatmap['total'],
            /** Promedio por tramo con actividad: referencia del tooltip («1,8× un tramo promedio»). */
            'average' => $activeSlots > 0 ? round($heatmap['total'] / $activeSlots, 2) : 0,
            'weekdays' => OperationsDashboardAnalytics::WEEKDAYS,
            'byWeekday' => $byWeekday,
            'busiest' => $busiest,
            'busiestDay' => $heatmap['total'] > 0 ? (int) array_search(max($byWeekday), $byWeekday, true) : null,
            'busiestHour' => $heatmap['total'] > 0 ? (int) array_search(max($byHour), $byHour, true) : null,
            'periodKey' => $period->cacheKey(),
        ];
    }
}
