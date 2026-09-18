<?php

declare(strict_types=1);

use App\Filament\Actions\ImportAction;

// `ImportAction::make()` resuelve el traductor del contenedor, y tests/Unit no
// arranca la aplicación por defecto. Solo lee: no toca base de datos.
uses(Tests\TestCase::class);

/**
 * Detección de encoding de los CSV que importa el sistema.
 *
 * Filament prueba `['UTF-8', 'SJIS-win', 'EUC-KR', 'ISO-8859-1', ...]` y se
 * queda con el primero que valide. Un CSV Latin-1 con eñes valida como
 * SJIS-win —en Shift-JIS `\xF1` abre una secuencia de dos bytes—, así que se
 * convertía desde japonés y la eñe desaparecía junto con la letra siguiente:
 * «BRICEÑO SILVA» llegaba a la base como «Brice Silva».
 */
function encodingDetectado(string $contenido): ?string
{
    $recurso = fopen('php://temp', 'r+');
    fwrite($recurso, $contenido);
    rewind($recurso);

    $metodo = new ReflectionMethod(ImportAction::class, 'detectCsvEncoding');
    $metodo->setAccessible(true);

    $detectado = $metodo->invoke(ImportAction::make('test'), $recurso);

    fclose($recurso);

    return $detectado;
}

it('no confunde un CSV Latin-1 con eñes con un archivo japonés', function (): void {
    // Exactamente los bytes del archivo del cliente: «Briceño» en Latin-1.
    $latin1 = "last_name;first_name\r\n".mb_convert_encoding('Briceño Silva', 'Windows-1252', 'UTF-8').";Albert\r\n";

    expect(mb_check_encoding($latin1, 'UTF-8'))->toBeFalse()
        // La trampa: el mismo contenido valida como Shift-JIS.
        ->and(mb_check_encoding($latin1, 'SJIS-win'))->toBeTrue()
        ->and(encodingDetectado($latin1))->toBe('Windows-1252');
});

it('conserva UTF-8 cuando el archivo ya viene en UTF-8', function (): void {
    expect(encodingDetectado("last_name;first_name\r\nBriceño Silva;Albert\r\n"))->toBe('UTF-8');
});

it('cae en Windows-1252 antes que perder caracteres', function (): void {
    // Bytes que no son UTF-8 válido: es mejor interpretar mal una tilde que
    // elegir un encoding multibyte y comerse letras.
    expect(encodingDetectado("last_name\r\n\x80\x9d\xf1\r\n"))->toBe('Windows-1252');
});

it('la eñe sobrevive la conversión completa del CSV', function (): void {
    $ruta = tempnam(sys_get_temp_dir(), 'padron').'.csv';

    file_put_contents(
        $ruta,
        "last_name;first_name\r\n".mb_convert_encoding("Briceño Silva;Albert Jhosmel\r\nMuñoz Peña;José\r\n", 'Windows-1252', 'UTF-8'),
    );

    $recurso = fopen($ruta, 'r');

    \League\Csv\CharsetConverter::register();
    stream_filter_append(
        $recurso,
        \League\Csv\CharsetConverter::getFiltername(encodingDetectado(file_get_contents($ruta)), 'UTF-8'),
        STREAM_FILTER_READ,
    );

    $lector = \League\Csv\Reader::createFromStream($recurso);
    $lector->setDelimiter(';');
    $lector->setHeaderOffset(0);

    $apellidos = array_column(iterator_to_array((new \League\Csv\Statement)->process($lector)->getRecords()), 'last_name');

    fclose($recurso);
    @unlink($ruta);

    expect($apellidos)->toBe(['Briceño Silva', 'Muñoz Peña'])
        ->and(mb_check_encoding($apellidos[0], 'UTF-8'))->toBeTrue();
});

it('la acción de importación del repo declara su propio orden de encodings', function (): void {
    $accion = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Actions/ImportAction.php');

    expect($accion)->toContain('protected function detectCsvEncoding(mixed $resource): ?string');

    // Se compara la constante y no el archivo completo: los multibyte asiáticos
    // de Filament se nombran en el comentario que explica por qué quedan fuera.
    preg_match('/CSV_ENCODINGS = \[(.*?)\];/s', $accion, $constante);

    expect($constante[1] ?? '')
        ->toContain("'UTF-8',")
        ->toContain("'Windows-1252',")
        ->toContain("'ISO-8859-1',")
        ->not->toContain('SJIS-win')
        ->not->toContain('EUC-KR');

    // Windows-1252 antes que ISO-8859-1: es su superconjunto y cubre las
    // comillas tipográficas que mete Excel.
    expect(strpos($constante[1], "'Windows-1252'"))
        ->toBeLessThan(strpos($constante[1], "'ISO-8859-1'"));
});
