<?php

declare(strict_types=1);

it('registra la acción en el log con el mensaje esperado', function (): void {
    $path = dirname(__DIR__, 2).'/app/Services/TdevReports/TdevReportComisionStatusUpdater.php';
    $src = file_get_contents($path);

    expect($src)->toContain('TDEV: estatus de comisión actualizado desde tabla Filament')
        ->and($src)->toContain('estatus_comision_anterior')
        ->and($src)->toContain('estatus_comision_nuevo');
});

it('expone la acción de columna en la tabla TDEV', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/TdevReports/Tables/TdevReportsTable.php';
    $src = file_get_contents($path);

    expect($src)->toContain('actualizarEstatusComisionTdev')
        ->and($src)->toContain('TdevReportComisionStatusUpdater::apply');
});
