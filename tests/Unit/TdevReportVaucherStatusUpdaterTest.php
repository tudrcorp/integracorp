<?php

declare(strict_types=1);

use App\Enums\StatusVaucher;
use App\Services\TdevReports\TdevReportVaucherStatusUpdater;

it('al elegir Anulado incluye pago y comisión en Anulado', function (): void {
    expect(TdevReportVaucherStatusUpdater::attributesForSelection(StatusVaucher::Anulado))->toBe([
        'estatus_vaucher' => 'ANULADO',
        'estatus_pago' => 'ANULADO',
        'estatus_comision' => 'ANULADO',
    ]);
});

it('al elegir Activo solo actualiza el voucher', function (): void {
    expect(TdevReportVaucherStatusUpdater::attributesForSelection(StatusVaucher::Activo))->toBe([
        'estatus_vaucher' => 'ACTIVO',
    ]);
});

it('expone la acción de columna en la tabla TDEV', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/TdevReports/Tables/TdevReportsTable.php';
    $src = file_get_contents($path);

    expect($src)->toContain('actualizarEstatusVaucherTdev')
        ->and($src)->toContain('TdevReportVaucherStatusUpdater::apply')
        ->and($src)->toContain('observacion_anulacion')
        ->and($src)->toContain('TdevReportProcessNotesModalActions');
});
