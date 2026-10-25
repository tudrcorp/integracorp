<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use Filament\Widgets\Widget;

/**
 * Indicadores principales del período, con variación contra el período anterior
 * y la tendencia diaria en miniatura.
 */
class OperationsKpiTiles extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected string $view = 'filament.operations.dashboard.kpi-tiles';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $data = OperationsDashboardAnalytics::kpis($period);
        $current = $data['current'];
        $previous = $data['previous'];

        $tiles = [
            self::tile('services', 'Servicios registrados', $current['services'], $previous['services'], 'heroicon-o-clipboard-document-list', 'Servicios con fecha en el período, de cualquier estatus.', $data['sparklines']['services']),
            self::tile('finished', 'Servicios finalizados', $current['finished'], $previous['finished'], 'heroicon-o-check-badge', 'Servicios del período que ya están FINALIZADOS.', $data['sparklines']['finished']),
            self::tile('close_rate', 'Tasa de cierre', $current['close_rate'], $previous['close_rate'], 'heroicon-o-chart-pie', 'Finalizados sobre registrados en el período. La variación está en puntos porcentuales.', [], isPercent: true),
            self::tile('open', 'En curso', $current['open'], $previous['open'], 'heroicon-o-arrow-path', 'Servicios del período que siguen PENDIENTES o EN GESTIÓN. Aquí, bajar es mejor.', [], lowerIsBetter: true),
            self::tile('patients', 'Pacientes atendidos', $current['patients'], $previous['patients'], 'heroicon-o-user-group', 'Pacientes distintos con al menos un servicio en el período.', $data['sparklines']['patients']),
            self::tile('cases', 'Casos atendidos', $current['cases'], $previous['cases'], 'heroicon-o-folder-open', 'Casos de telemedicina distintos con servicios en el período.', $data['sparklines']['cases']),
        ];

        return [
            'tiles' => $tiles,
            'compare' => $period->compare,
            'previousLabel' => $period->previousLabel(),
            'periodKey' => $period->cacheKey(),
        ];
    }

    /**
     * @param  list<int>  $sparkline
     * @return array<string, mixed>
     */
    protected static function tile(
        string $key,
        string $label,
        int|float $value,
        int|float $previous,
        string $icon,
        string $hint,
        array $sparkline,
        bool $isPercent = false,
        bool $lowerIsBetter = false,
        ?int $decimals = null,
        string $suffix = '',
        string $prefix = '',
        ?string $note = null,
        bool $snapshot = false,
    ): array {
        $delta = self::delta($value, $previous, $isPercent);
        $improved = $delta['direction'] === 'flat' ? null : (($delta['direction'] === 'up') !== $lowerIsBetter);

        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'decimals' => $decimals ?? ($isPercent ? 1 : 0),
            'suffix' => $isPercent ? ' %' : $suffix,
            'prefix' => $prefix,
            'note' => $note,
            'snapshot' => $snapshot,
            'previous' => $previous,
            'icon' => $icon,
            'hint' => $hint,
            'delta' => $delta,
            'improved' => $improved,
            'sparkline' => self::sparklinePath($sparkline),
            'progress' => $isPercent ? max(0, min(100, (float) $value)) : null,
        ];
    }

    /**
     * @return array{direction: string, text: string}
     */
    public static function delta(int|float $value, int|float $previous, bool $isPercent = false): array
    {
        $difference = $value - $previous;

        if (abs($difference) < 0.05) {
            return ['direction' => 'flat', 'text' => 'Sin cambios'];
        }

        $direction = $difference > 0 ? 'up' : 'down';

        if ($isPercent) {
            return ['direction' => $direction, 'text' => ($difference > 0 ? '+' : '−').self::number(abs($difference), 1).' pp'];
        }

        if ($previous <= 0) {
            return ['direction' => $direction, 'text' => 'Nuevo'];
        }

        $percent = $difference * 100 / $previous;

        return ['direction' => $direction, 'text' => ($difference > 0 ? '+' : '−').self::number(abs($percent), abs($percent) < 10 ? 1 : 0).' %'];
    }

    /**
     * Trazo SVG de la tendencia en una caja de 100 × 32. Vacío si no hay datos.
     *
     * @param  list<int|float>  $values
     */
    public static function sparklinePath(array $values): ?string
    {
        $count = count($values);

        if ($count < 2 || max($values) <= 0) {
            return null;
        }

        $max = max($values);
        $points = [];

        foreach ($values as $index => $value) {
            $x = round($index * 100 / ($count - 1), 2);
            $y = round(30 - ($value * 28 / $max), 2);
            $points[] = ($index === 0 ? 'M' : 'L').$x.' '.$y;
        }

        return implode(' ', $points);
    }

    public static function number(int|float $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}
