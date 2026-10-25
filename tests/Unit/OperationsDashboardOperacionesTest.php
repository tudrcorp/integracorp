<?php

declare(strict_types=1);

use App\Filament\Operations\Pages\DashboardOperaciones;
use Filament\Pages\Dashboard;

it('registra la página Dashboard Operaciones con sus widgets', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Pages/DashboardOperaciones.php');

    expect(class_exists(DashboardOperaciones::class))->toBeTrue()
        ->and(is_subclass_of(DashboardOperaciones::class, Dashboard::class))->toBeTrue()
        ->and($page)->toContain("protected static ?string \$navigationLabel = 'Dashboard Operaciones'")
        ->and($page)->toContain("protected static string \$routePath = 'dashboard-operaciones'")
        // Rediseño del 24/10/2026: período común, comparación y modo presentación.
        ->and($page)->toContain('use HasOperationsDashboardFilters;')
        ->and($page)->toContain('OperationsKpiTiles::class')
        ->and($page)->toContain('OperationsServicesTrendChart::class')
        ->and($page)->toContain('OperationsServicesStatusChart::class')
        ->and($page)->toContain('OperationsServiceTypeChart::class')
        ->and($page)->toContain('OperationsBusinessLineSplit::class')
        ->and($page)->toContain('OperationsStateRanking::class')
        ->and($page)->toContain('OperationsWorkloadHeatmap::class')
        ->and($page)->toContain('OperationsAgeChart::class')
        ->and($page)->toContain('OperationsTopServicesRanking::class')
        ->and($page)->toContain('OperationsTopPatientsRanking::class')
        ->and($page)->toContain('OperationsDashboardDetail::class')
        ->and($page)->toContain("'xl' => 12");
});

it('widget de pacientes atendidos soporta drill-down por paciente', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/Dashboard/TopPatientsMedicalDischargeChart.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/widgets/top-patients-medical-discharge-chart.blade.php');

    expect($widget)
        ->toContain('public function handleChartClick(array $payload)')
        ->toContain('public function resetToPatientsOverview()')
        ->toContain('topPatientsByMedicalDischargeCases(20)')
        ->toContain('medicalDischargeCasesForPatient($patientKey)')
        ->toContain('medicalDischargeCaseHoverLines')
        ->toContain("'summaries' => \$summaries")
        ->toContain('afterBody: function(context)')
        ->toContain('$wire.handleChartClick({')
        ->toContain('chartPatientKeys');

    expect($view)
        ->toContain('@entangle(\'selectedPatientKey\').live')
        ->toContain('wire:click="resetToPatientsOverview"')
        ->toContain('x-transition:enter');
});

it('widget de servicios finalizados agrupa la tabla de hechos por mes', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/Dashboard/FinishedServicesMonthlyChart.php');

    expect($widget)
        ->toContain('finishedServicesMonthlyCounts($year)')
        ->toContain('tabla de estadísticas')
        ->not->toContain('coordinationServicesQuery()');
});

it('widgets de volumen de servicios leen la tabla de hechos', function (): void {
    $base = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/Dashboard/OperationsFactBarChart.php');
    $status = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/Dashboard/ServicesByStatusChart.php');
    $line = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/Dashboard/ServicesByBusinessLineChart.php');
    $type = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/Dashboard/ServicesByServiceTypeChart.php');

    expect($base)
        ->toContain("return 'bar'")
        ->toContain('protected static bool $isDiscovered = false');

    expect($status)
        ->toContain('countsByServiceStatus')
        ->toContain('Total de servicios por estatus');

    expect($line)
        ->toContain('countsByBusinessLine')
        ->toContain('Total de servicios por línea de negocio');

    expect($type)
        ->toContain('countsByServiceType')
        ->toContain('Total de servicios por tipo de servicio');
});

it('widgets del dashboard no se auto-descubren en el dashboard principal', function (): void {
    foreach ([
        'OperationsDashboardStatsOverview',
        'TopPatientsMedicalDischargeChart',
        'FinishedServicesMonthlyChart',
        'ServicesByStatusChart',
        'ServicesByBusinessLineChart',
        'ServicesByServiceTypeChart',
    ] as $widget) {
        $contents = file_get_contents(dirname(__DIR__, 2)."/app/Filament/Operations/Widgets/Dashboard/{$widget}.php");

        expect($contents)->toContain('protected static bool $isDiscovered = false');
    }
});
