<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\TdevReports\Actions\TdevReportPaymentModalActions;
use App\Filament\Administration\Resources\TdevReports\Schemas\TdevReportInfolist;

it('configura el infolist de reporte TDEV', function () {
    expect(class_exists(TdevReportInfolist::class))->toBeTrue()
        ->and(method_exists(TdevReportInfolist::class, 'configure'))->toBeTrue();
});

it('resalta las secciones de pago del voucher y comisiones alineadas al modelo', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/TdevReports/Schemas/TdevReportInfolist.php';
    $src = file_get_contents($path);

    expect($src)->toContain('HIGHLIGHT_PAYMENT_SECTION')
        ->and($src)->toContain('HIGHLIGHT_COMMISSION_SECTION')
        ->and($src)->toContain('fecha_pago_vaucher_credito')
        ->and($src)->toContain('porcentaje_comision')
        ->and($src)->toContain('formas_pago_comision');
});

it('expone la acción modal Registrar pago de TDEV con estilo iOS', function (): void {
    expect(class_exists(TdevReportPaymentModalActions::class))->toBeTrue()
        ->and(method_exists(TdevReportPaymentModalActions::class, 'makeRegistrarPagoAction'))->toBeTrue();

    $tablePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/TdevReports/Tables/TdevReportsTable.php';
    $tableSrc = file_get_contents($tablePath);

    expect($tableSrc)->toContain('makeRegistrarPagoAction')
        ->and($tableSrc)->toContain('TdevReportPaymentModalActions');
});

it('incluye vista previa del comprobante de pago en el infolist', function (): void {
    $infolistPath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/TdevReports/Schemas/TdevReportInfolist.php';
    $infolistSrc = file_get_contents($infolistPath);

    expect($infolistSrc)->toContain('previewComprobantePago')
        ->and($infolistSrc)->toContain('comprobante-pago-preview');

    $bladePath = dirname(__DIR__, 2).'/resources/views/filament/administration/tdev-reports/comprobante-pago-preview.blade.php';

    expect(file_exists($bladePath))->toBeTrue();
});
