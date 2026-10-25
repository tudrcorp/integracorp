<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\Dashboard;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Operations\Dashboard\OperationsDashboardAnalytics;
use Filament\Widgets\Widget;

/**
 * Corporativos frente a individuales. Dos categorías no piden un pastel: una
 * barra de composición con los números al lado se lee mejor.
 */
class OperationsBusinessLineSplit extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 5;

    protected string $view = 'filament.operations.dashboard.business-line';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    /**
     * @var array<string, string> línea => token de color
     */
    private const TOKENS = [
        'CORPORATIVOS' => 'series1',
        'INDIVIDUALES' => 'series2',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $counts = OperationsDashboardAnalytics::byBusinessLine($period);
        $total = array_sum($counts);

        $segments = [];

        foreach ($counts as $label => $value) {
            $segments[] = [
                'label' => (string) $label,
                'value' => (int) $value,
                'share' => $total > 0 ? round($value * 100 / $total, 1) : 0,
                'percent' => $total > 0 ? number_format($value * 100 / $total, 1, ',', '.').' %' : '0 %',
                'token' => self::TOKENS[$label] ?? 'muted',
            ];
        }

        $patients = OperationsDashboardAnalytics::kpis($period)['current']['patients'];

        return [
            'segments' => $segments,
            'total' => $total,
            'patients' => $patients,
            'periodKey' => $period->cacheKey(),
        ];
    }
}
