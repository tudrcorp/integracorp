<?php

declare(strict_types=1);

use App\Models\TdevReport;

it('el modelo TDEV incluye observaciones_proceso en fillable', function (): void {
    expect(in_array('observaciones_proceso', (new TdevReport)->getFillable(), true))->toBeTrue();
});

it('el appender de proceso usa el mismo merge que helpdesk', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/TdevReportProcessObservationAppender.php';
    $src = file_get_contents($path);

    expect($src)->toContain('HelpdeskObservationAppender::mergeObservation')
        ->and($src)->toContain('HelpdeskNoteHtmlSanitizer::sanitize');
});

it('la migración añade la columna observaciones_proceso', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_04_14_121050_add_observaciones_proceso_to_tdev_reports_table.php';
    $src = file_get_contents($path);

    expect($src)->toContain('observaciones_proceso');
});

it('el updater acepta observación opcional al anular', function (): void {
    $path = dirname(__DIR__, 2).'/app/Services/TdevReports/TdevReportVaucherStatusUpdater.php';
    $src = file_get_contents($path);

    expect($src)->toContain('?string $observacionAnulacionHtml')
        ->and($src)->toContain('TdevReportProcessObservationAppender::append');
});
