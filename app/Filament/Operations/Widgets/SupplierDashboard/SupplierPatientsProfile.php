<?php

declare(strict_types=1);

namespace App\Filament\Operations\Widgets\SupplierDashboard;

use App\Filament\Operations\Widgets\Dashboard\Concerns\InteractsWithOperationsDashboard;
use App\Support\Operations\Dashboard\SupplierDashboardAnalytics;
use Filament\Widgets\Widget;

/**
 * Quiénes son los pacientes del período: composición por sexo y franjas de edad.
 */
class SupplierPatientsProfile extends Widget
{
    use InteractsWithOperationsDashboard;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 9;

    protected string $view = 'filament.operations.dashboard.patients-profile';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    /**
     * Sexo → token de color (azul y naranja: el par validado).
     *
     * @var array<string, string>
     */
    private const SEX_TOKENS = [
        'Femenino' => 'series2',
        'Masculino' => 'series1',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $data = SupplierDashboardAnalytics::patients($period);
        $sexTotal = max(1, array_sum($data['sexes']));
        $ageMax = max($data['ages'] ?: [0]);
        $ageTotal = max(1, array_sum($data['ages']));

        return [
            'total' => $data['total'],
            'periodKey' => $period->cacheKey(),
            'sexes' => array_map(static fn (string $label, int $value): array => [
                'label' => $label,
                'value' => $value,
                'share' => round($value * 100 / $sexTotal, 1),
                'percent' => number_format($value * 100 / $sexTotal, 1, ',', '.').' %',
                'token' => self::SEX_TOKENS[$label] ?? 'muted',
            ], array_keys($data['sexes']), array_values($data['sexes'])),
            'ages' => array_map(static fn (string $label, int $value): array => [
                'label' => $label,
                'value' => $value,
                'width' => $ageMax > 0 ? round($value * 100 / $ageMax, 1) : 0,
                'percent' => number_format($value * 100 / $ageTotal, 1, ',', '.').' %',
            ], array_keys($data['ages']), array_values($data['ages'])),
        ];
    }
}
