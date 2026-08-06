<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\ReportSiniestralidadAction;
use App\Filament\Operations\Resources\TelemedicinePatients\Pages\ListTelemedicinePatients;
use App\Filament\Operations\Resources\TelemedicinePatients\Pages\ViewTelemedicinePatient;
use App\Http\Controllers\TelemedicinePatientSiniestralidadReportController;
use App\Support\Filament\Operations\OperationsSupplierScope;

it('registra el boton de reporte solo para analistas tdg y rutas csv/pdf', function (): void {
    $root = dirname(__DIR__, 2);
    $action = file_get_contents($root.'/app/Filament/Operations/Resources/TelemedicinePatients/Actions/ReportSiniestralidadAction.php');
    $listPage = file_get_contents($root.'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/ListTelemedicinePatients.php');
    $viewPage = file_get_contents($root.'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/ViewTelemedicinePatient.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/TelemedicinePatientSiniestralidadReportController.php');
    $routes = file_get_contents($root.'/routes/web.php');
    $service = file_get_contents($root.'/app/Services/PatientSiniestralidadReportService.php');
    $blade = file_get_contents($root.'/resources/views/documents/patient-siniestralidad-report.blade.php');

    expect(ReportSiniestralidadAction::class)->toBeString()
        ->and(ListTelemedicinePatients::class)->toBeString()
        ->and(ViewTelemedicinePatient::class)->toBeString()
        ->and(TelemedicinePatientSiniestralidadReportController::class)->toBeString()
        ->and($action)->toContain('report_siniestralidad')
        ->and($action)->toContain('Reporte siniestralidad')
        ->and($action)->toContain('authenticatedUserIsTdgAnalyst')
        ->and($action)->toContain('PatientSiniestralidadReportService::DEFAULT_TOP_N')
        ->and($action)->toContain("Radio::make('format')")
        ->and($action)->toContain("'pdf'")
        ->and($action)->toContain("'csv'")
        ->and($action)->toContain('window.open')
        ->and($action)->toContain('operations.telemedicine-patients.siniestralidad.preview')
        ->and($action)->toContain('operations.telemedicine-patients.siniestralidad.csv')
        ->and($action)->not->toContain('Reporte de siniestralidad listo')
        ->and($action)->not->toContain("->label('Descargar PDF')")
        ->and($listPage)->toContain('ReportSiniestralidadAction::make()')
        ->and($viewPage)->toContain('ReportSiniestralidadAction::make()')
        ->and($controller)->toContain('ensureTdgAnalyst')
        ->and($controller)->toContain(OperationsSupplierScope::class)
        ->and($routes)->toContain('operations.telemedicine-patients.siniestralidad.preview')
        ->and($routes)->toContain('operations.telemedicine-patients.siniestralidad.pdf')
        ->and($routes)->toContain('operations.telemedicine-patients.siniestralidad.csv')
        ->and($service)->toContain("where('status', self::STATUS_FINALIZED)")
        ->and($service)->toContain('COALESCE(SUM(bill_price), 0)')
        ->and($service)->toContain('orderByDesc(\'claims_count\')')
        ->and($blade)->toContain('Top')
        ->and($blade)->toContain('pacientes más siniestrosos')
        ->and($blade)->toContain('Monto total (USD)');
});
