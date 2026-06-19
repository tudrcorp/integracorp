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
        ->and($page)->toContain('OperationsDashboardStatsOverview::class')
        ->and($page)->toContain('TopPatientsMedicalDischargeChart::class')
        ->and($page)->toContain('FinishedServicesMonthlyChart::class');
});

it('widget de pacientes atendidos soporta drill-down por paciente', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/Dashboard/TopPatientsMedicalDischargeChart.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/widgets/top-patients-medical-discharge-chart.blade.php');

    expect($widget)
        ->toContain('public function handleChartClick(array $payload)')
        ->toContain('public function resetToPatientsOverview()')
        ->toContain('topPatientsByMedicalDischargeCases(20)')
        ->toContain('medicalDischargeCasesForPatient($patientId)')
        ->toContain('$wire.handleChartClick({')
        ->toContain('chartPatientIds');

    expect($view)
        ->toContain('@entangle(\'selectedPatientId\').live')
        ->toContain('wire:click="resetToPatientsOverview"')
        ->toContain('x-transition:enter');
});

it('widget de servicios finalizados agrupa coordinaciones por mes', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/Dashboard/FinishedServicesMonthlyChart.php');

    expect($widget)
        ->toContain("->where('status', 'FINALIZADO')")
        ->toContain('->perMonth()')
        ->toContain('coordinationServicesQuery()');
});

it('widgets del dashboard no se auto-descubren en el dashboard principal', function (): void {
    foreach ([
        'OperationsDashboardStatsOverview',
        'TopPatientsMedicalDischargeChart',
        'FinishedServicesMonthlyChart',
    ] as $widget) {
        $contents = file_get_contents(dirname(__DIR__, 2)."/app/Filament/Operations/Widgets/Dashboard/{$widget}.php");

        expect($contents)->toContain('protected static bool $isDiscovered = false');
    }
});
