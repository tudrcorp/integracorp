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

it('envuelve un identificador numérico largo en formula de texto para que Excel no lo pase a notación científica', function (): void {
    expect(CsvExportStream::forceTextForExcel('01040006123456789012'))
        ->toBe('="01040006123456789012"');
});

it('deja intactos los valores que no son puramente numéricos', function (): void {
    expect(CsvExportStream::forceTextForExcel('AGENTICOVA21XXX'))
        ->toBe('AGENTICOVA21XXX');

    expect(CsvExportStream::forceTextForExcel('GB29-NWBK-6016-1331-9268-19'))
        ->toBe('GB29-NWBK-6016-1331-9268-19');
});

it('devuelve cadena vacía cuando el valor es nulo o vacío', function (): void {
    expect(CsvExportStream::forceTextForExcel(null))->toBe('');
    expect(CsvExportStream::forceTextForExcel(''))->toBe('');
});
