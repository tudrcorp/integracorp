<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineInformeSignatureStamp;
use Dompdf\Dompdf;

/**
 * El sello se vuelca a un temporal para que `Canvas::image()` pueda dibujarlo.
 * Ese temporal debe ser propio de cada render: cuando la ruta salía del hash del
 * contenido, dos informes del mismo médico compartían archivo y el primero en
 * terminar borraba el sello que el segundo aún no había dibujado.
 */
const SELLO_PNG_1X1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

function informeSignatureStampDompdf(): Dompdf
{
    $dompdf = new Dompdf;
    $dompdf->loadHtml('<p>Informe de prueba</p>');
    $dompdf->render();

    return $dompdf;
}

/**
 * @return array<string, mixed>
 */
function informeSignatureStampData(): array
{
    return [
        'doctor_name' => 'DOCTOR DE PRUEBA',
        'code_mpps' => '123456',
        'signature' => SELLO_PNG_1X1,
    ];
}

it('da a cada render su propio temporal aunque el sello sea el mismo', function (): void {
    $data = informeSignatureStampData();

    $primero = TelemedicineInformeSignatureStamp::applyTo(informeSignatureStampDompdf(), $data);
    $segundo = TelemedicineInformeSignatureStamp::applyTo(informeSignatureStampDompdf(), $data);

    expect($primero)->toBeString()
        ->and($segundo)->toBeString()
        ->and($primero)->not->toBe($segundo)
        ->and(is_file($primero))->toBeTrue()
        ->and(is_file($segundo))->toBeTrue();

    TelemedicineInformeSignatureStamp::cleanUp($primero);
    TelemedicineInformeSignatureStamp::cleanUp($segundo);
});

it('la limpieza de un render no deja sin sello al que va en paralelo', function (): void {
    $data = informeSignatureStampData();

    $enCurso = TelemedicineInformeSignatureStamp::applyTo(informeSignatureStampDompdf(), $data);
    $queTermina = TelemedicineInformeSignatureStamp::applyTo(informeSignatureStampDompdf(), $data);

    TelemedicineInformeSignatureStamp::cleanUp($queTermina);

    expect(is_file($queTermina))->toBeFalse()
        ->and(is_file($enCurso))->toBeTrue('El render en curso se quedó sin sello por la limpieza de otro.');

    TelemedicineInformeSignatureStamp::cleanUp($enCurso);
    expect(is_file($enCurso))->toBeFalse();
});

it('el temporal conserva el contenido del sello', function (): void {
    $path = TelemedicineInformeSignatureStamp::applyTo(informeSignatureStampDompdf(), informeSignatureStampData());

    $esperado = base64_decode(substr(SELLO_PNG_1X1, strpos(SELLO_PNG_1X1, ',') + 1), true);

    expect(file_get_contents($path))->toBe($esperado)
        ->and(@getimagesize($path))->toBeArray();

    TelemedicineInformeSignatureStamp::cleanUp($path);
});

it('no crea temporal si el médico no tiene sello cargado', function (): void {
    $path = TelemedicineInformeSignatureStamp::applyTo(informeSignatureStampDompdf(), [
        'doctor_name' => 'DOCTOR SIN SELLO',
        'code_mpps' => '123456',
        'signature' => null,
    ]);

    expect($path)->toBeNull();
});

it('la limpieza ignora rutas que no creó el estampado', function (): void {
    $ajeno = sys_get_temp_dir().'/archivo-ajeno-'.bin2hex(random_bytes(4)).'.png';
    file_put_contents($ajeno, 'contenido ajeno');

    TelemedicineInformeSignatureStamp::cleanUp($ajeno);
    TelemedicineInformeSignatureStamp::cleanUp(null);
    TelemedicineInformeSignatureStamp::cleanUp('');

    expect(is_file($ajeno))->toBeTrue('cleanUp borró un archivo que no era suyo.');

    @unlink($ajeno);
});
