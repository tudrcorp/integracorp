<?php

declare(strict_types=1);

use App\Support\CsvExportStream;

it('escribe el BOM UTF-8 al abrir el stream de salida para compatibilidad con Excel', function (): void {
    ob_start();

    $handle = CsvExportStream::openOutput();

    expect($handle)->not->toBeFalse();

    fputcsv($handle, ['Atención', 'Ubicación']);

    fclose($handle);

    $content = ob_get_clean();

    expect($content)->toStartWith(CsvExportStream::UTF8_BOM);
    expect($content)->toContain('Atención');
    expect($content)->toContain('Ubicación');
});
