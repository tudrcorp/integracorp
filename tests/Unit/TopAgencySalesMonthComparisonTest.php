<?php

declare(strict_types=1);

use App\Filament\Administration\Widgets\TotalSaleMonthlyNowVsLastAgency;
use App\Support\Charts\TopAgencySalesMonthComparison;
use Carbon\Carbon;
use Livewire\Livewire;

uses(Tests\TestCase::class);

it('devuelve como máximo 10 agencias ordenadas por ventas del mes actual', function (): void {
    $current = collect([
        (object) ['agency_code' => 'A1', 'label' => 'A', 'total' => 50],
        (object) ['agency_code' => 'A2', 'label' => 'B', 'total' => 200],
        (object) ['agency_code' => 'A3', 'label' => 'C', 'total' => 150],
        (object) ['agency_code' => 'A4', 'label' => 'D', 'total' => 300],
        (object) ['agency_code' => 'A5', 'label' => 'E', 'total' => 100],
        (object) ['agency_code' => 'A6', 'label' => 'F', 'total' => 400],
        (object) ['agency_code' => 'A7', 'label' => 'G', 'total' => 90],
        (object) ['agency_code' => 'A8', 'label' => 'H', 'total' => 80],
        (object) ['agency_code' => 'A9', 'label' => 'I', 'total' => 70],
        (object) ['agency_code' => 'A10', 'label' => 'J', 'total' => 60],
        (object) ['agency_code' => 'A11', 'label' => 'K', 'total' => 55],
    ]);

    $previous = collect([
        (object) ['agency_code' => 'A1', 'label' => 'A', 'total' => 10],
        (object) ['agency_code' => 'A6', 'label' => 'F', 'total' => 5],
    ]);

    $result = TopAgencySalesMonthComparison::mergeAndTakeTopByCurrentMonth($current, $previous, 10);

    expect($result)->toHaveCount(10)
        ->and($result->first()['label'])->toBe('F')
        ->and($result->first()['agency_code'])->toBe('A6')
        ->and($result->first()['current'])->toBe(400.0)
        ->and($result->pluck('label')->all())->toBe(['F', 'D', 'B', 'C', 'E', 'G', 'H', 'I', 'J', 'K']);
});

it('desempata por ventas del mes anterior cuando el mes actual es igual', function (): void {
    $current = collect([
        (object) ['agency_code' => 'A1', 'label' => 'A', 'total' => 100],
        (object) ['agency_code' => 'A2', 'label' => 'B', 'total' => 100],
    ]);

    $previous = collect([
        (object) ['agency_code' => 'A1', 'label' => 'A', 'total' => 50],
        (object) ['agency_code' => 'A2', 'label' => 'B', 'total' => 80],
    ]);

    $result = TopAgencySalesMonthComparison::mergeAndTakeTopByCurrentMonth($current, $previous, 10);

    expect($result->first()['label'])->toBe('B')
        ->and($result->last()['label'])->toBe('A');
});

it('unifica agency_code con distinta capitalizacion', function (): void {
    $current = collect([
        (object) ['agency_code' => 'tdg-151', 'label' => 'Grupo Olivo', 'total' => 10],
    ]);

    $previous = collect([
        (object) ['agency_code' => 'TDG-151', 'label' => 'Grupo Olivo', 'total' => 20],
    ]);

    $result = TopAgencySalesMonthComparison::mergeAndTakeTopByCurrentMonth($current, $previous, 10);

    expect($result)->toHaveCount(1)
        ->and($result->first()['agency_code'])->toBe('TDG-151')
        ->and($result->first()['current'])->toBe(10.0)
        ->and($result->first()['previous'])->toBe(20.0);
});

it('expone el widget de barras en el panel de administracion', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Widgets/TotalSaleMonthlyNowVsLastAgency.php');
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/AdministrationPanelProvider.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/administration/widgets/agency-sales-mom-line-chart.blade.php');

    expect($widget)
        ->toContain('protected function getType(): string')
        ->toContain("return 'bar';")
        ->toContain('TopAgencySalesMonthComparison::mergeAndTakeTopByCurrentMonth')
        ->toContain('TopAgencySalesMonthComparison::takeTopByTotal')
        ->toContain('getYearToDateChartData')
        ->toContain('total_amount')
        ->toContain('Mes en curso')
        ->toContain('toggleComparisonMonth')
        ->toContain('comparisonMonths')
        ->toContain('agency-sales-mom-line-chart')
        ->toContain("'360px'")
        ->toContain("whereNull('sales.agent_id')")
        ->toContain("'TDG-100'")
        ->toContain("orWhere('sales.owner_code', '<>', 'TDG-100')")
        ->and($view)
        ->toContain('toggleComparisonMonth')
        ->toContain('Comparar con meses anteriores')
        ->toContain('getYearToDateChartData')
        ->toContain('getYearToDateChartHeading')
        ->toContain('agency-sales-ytd-chart-static')
        ->toContain('fi-admin-agency-month-filter')
        ->toContain('toggleMonthlyChart')
        ->toContain('monthlyChartExpanded')
        ->toContain('toggleYearChart')
        ->toContain('yearChartExpanded')
        ->toContain('Colapsado · ábrelo para ver el ranking anual')
        ->not->toContain('Siempre visible')
        ->and($widget)
        ->toContain('drawOnChartArea: true')
        ->toContain('monthlyChartExpanded = false')
        ->toContain('yearChartExpanded = false')
        ->and($provider)->toContain("discoverWidgets(in: app_path('Filament/Administration/Widgets')");
});

it('inicia el grafico mensual colapsado y permite expandirlo', function (): void {
    Livewire::test(TotalSaleMonthlyNowVsLastAgency::class)
        ->assertSet('monthlyChartExpanded', false)
        ->call('toggleMonthlyChart')
        ->assertSet('monthlyChartExpanded', true)
        ->call('toggleMonthlyChart')
        ->assertSet('monthlyChartExpanded', false);
});

it('inicia el ranking anual colapsado y permite expandirlo', function (): void {
    Livewire::test(TotalSaleMonthlyNowVsLastAgency::class)
        ->assertSet('yearChartExpanded', false)
        ->call('toggleYearChart')
        ->assertSet('yearChartExpanded', true)
        ->call('toggleYearChart')
        ->assertSet('yearChartExpanded', false);
});

it('toma el top por total anual acumulado', function (): void {
    $rows = collect([
        (object) ['agency_code' => 'A1', 'label' => 'Uno', 'total' => 100],
        (object) ['agency_code' => 'A2', 'label' => 'Dos', 'total' => 500],
        (object) ['agency_code' => 'A3', 'label' => 'Tres', 'total' => 0],
        (object) ['agency_code' => 'A4', 'label' => 'Cuatro', 'total' => 250],
    ]);

    $result = TopAgencySalesMonthComparison::takeTopByTotal($rows, 2);

    expect($result)->toHaveCount(2)
        ->and($result->first()['label'])->toBe('Dos')
        ->and($result->first()['total'])->toBe(500.0)
        ->and($result->last()['label'])->toBe('Cuatro');
});

it('arma el dataset del top 10 del año en curso', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 8, 6, 12));

    $component = Livewire::test(TotalSaleMonthlyNowVsLastAgency::class);
    $data = $component->instance()->getYearToDateChartData();

    expect($data)->toHaveKeys(['datasets', 'labels'])
        ->and($data['datasets'])->toHaveCount(1)
        ->and($data['datasets'][0]['label'])->toBe('Año 2026')
        ->and($component->instance()->getYearToDateChartHeading())->toBe('Top 10 del año 2026');

    Carbon::setTestNow();
});

it('el filtro mensual no altera el ranking anual', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 8, 6, 12));

    $component = Livewire::test(TotalSaleMonthlyNowVsLastAgency::class);
    $before = $component->instance()->getYearToDateChartData();

    $component->call('toggleComparisonMonth', '2025-3');

    $after = $component->instance()->getYearToDateChartData();
    $monthly = (new ReflectionClass($component->instance()))
        ->getMethod('getData');
    $monthly->setAccessible(true);
    $monthlyData = $monthly->invoke($component->instance());

    expect($component->get('comparisonMonths'))->toContain('2025-3')
        ->and($after)->toBe($before)
        ->and(collect($monthlyData['datasets'])->pluck('label')->all())
        ->toContain('Marzo 2025')
        ->and(collect($monthlyData['datasets'])->pluck('label')->all())
        ->not->toContain('Año 2026');

    Carbon::setTestNow();
});

it('inicia con el mes pasado seleccionado y permite agregar otro mes', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 8, 6, 12));

    $component = Livewire::test(TotalSaleMonthlyNowVsLastAgency::class);

    $previousKey = '2026-7';
    $extraKey = '2025-1';

    $component
        ->assertSet('comparisonMonths', [$previousKey])
        ->call('toggleComparisonMonth', $extraKey)
        ->assertSet('comparisonMonths', [$extraKey, $previousKey])
        ->call('toggleComparisonMonth', $extraKey)
        ->assertSet('comparisonMonths', [$previousKey]);

    $available = $component->instance()->getAvailableComparisonMonths();
    $keys = collect($available)->pluck('key')->all();

    expect($keys)->toContain('2025-1')
        ->and($keys)->toContain('2026-7')
        ->and($keys)->not->toContain('2026-8')
        ->and($keys[0])->toBe('2025-1')
        ->and(end($keys))->toBe('2026-7');

    Carbon::setTestNow();
});

it('ignora meses fuera del rango seleccionable', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 8, 6, 12));

    Livewire::test(TotalSaleMonthlyNowVsLastAgency::class)
        ->call('toggleComparisonMonth', '2026-8')
        ->assertSet('comparisonMonths', ['2026-7'])
        ->call('toggleComparisonMonth', '2024-12')
        ->assertSet('comparisonMonths', ['2026-7']);

    Carbon::setTestNow();
});
