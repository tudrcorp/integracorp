<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Services\PatientDetailedSiniestralidadReportService;
use App\Support\Charts\SvgLineChartRenderer;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

it('arma la serie mensual del ano en curso con servicios finalizados', function (): void {
    $services = collect([
        new OperationCoordinationService(['status' => 'FINALIZADO']),
        new OperationCoordinationService(['status' => 'FINALIZADO']),
        new OperationCoordinationService(['status' => 'FINALIZADO']),
        new OperationCoordinationService(['status' => 'PENDIENTE']),
    ]);

    $services[0]->created_at = Carbon::create(2026, 1, 10, 12);
    $services[1]->created_at = Carbon::create(2026, 1, 20, 12);
    $services[2]->created_at = Carbon::create(2026, 3, 5, 12);
    $services[3]->created_at = Carbon::create(2026, 2, 1, 12);

    $series = PatientDetailedSiniestralidadReportService::buildYearSeries($services, 2026, 3);

    expect($series['labels'])->toBe(['Ene', 'Feb', 'Mar'])
        ->and($series['values'])->toBe([2, 0, 1]);
});

it('genera un data uri de grafico de linea png o svg', function (): void {
    $dataUri = SvgLineChartRenderer::toPngDataUri(
        ['Ene', 'Feb', 'Mar'],
        [2, 0, 5],
        'Prueba',
        400,
        200,
    );

    expect($dataUri)->toStartWith('data:image/')
        ->and(strlen($dataUri))->toBeGreaterThan(50);
});

it('registra la record action y rutas del reporte detallado', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Tables/TelemedicinePatientsTable.php');
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/PatientDetailedSiniestralidadReportService.php');
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/patient-detailed-siniestralidad-report.blade.php');

    expect($table)->toContain('detailed_siniestralidad_report')
        ->and($table)->toContain('Reporte detallado')
        ->and($table)->toContain('authenticatedUserIsTdgAnalyst')
        ->and($table)->toContain('siniestralidad-detalle.preview')
        ->and($routes)->toContain('operations.telemedicine-patients.siniestralidad-detalle.preview')
        ->and($routes)->toContain('operations.telemedicine-patients.siniestralidad-detalle.pdf')
        ->and($service)->toContain('SvgLineChartRenderer::toPngDataUri')
        ->and($service)->toContain("where('status', self::STATUS_FINALIZED)")
        ->and($blade)->toContain('chart_data_uri')
        ->and($blade)->toContain('Detalle de servicios realizados')
        ->and($blade)->toContain('Costo total para la empresa');
});
