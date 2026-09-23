<?php

declare(strict_types=1);

use App\Support\PdfOpaqueImage;
use App\Support\Telemedicine\TelemedicineDoctorStamp;
use App\Support\Telemedicine\TelemedicineInformeSignatureStamp;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Las órdenes consultan los catálogos de cobertura. Sin config cacheada los
 * tests corren en sqlite en memoria, vacía: se crean catálogos mínimos para
 * que el test no dependa de la base de desarrollo.
 */
beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        return;
    }

    foreach (['telemedicine_list_laboratories', 'telemedicine_list_studies', 'telemedicine_list_specialists'] as $table) {
        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('name')->nullable();
                $blueprint->string('type')->nullable();
                $blueprint->timestamps();
            });
        }
    }
});

/**
 * El logo de los documentos de telemedicina salió en producción como letras
 * blancas sobre fondo negro: DomPDF dibujó la máscara de transparencia del PNG
 * en lugar del logo. Estas pruebas fijan que ninguna imagen de esos documentos
 * llegue a DomPDF con canal alfa, que es la ruta donde falla.
 */
function transparentPng(int $width = 40, int $height = 20): string
{
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 0, 0, 9, $height - 1, imagecolorallocatealpha($image, 0, 173, 239, 0));

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

/**
 * @return array{0: int, 1: int, 2: int}
 */
function pixelAt(string $png, int $x, int $y): array
{
    $image = imagecreatefromstring($png);
    $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));

    return [$color['red'], $color['green'], $color['blue']];
}

/**
 * @return list<array{width: int, height: int, colorspace: string, smask: bool}>
 */
function pdfImages(string $pdf): array
{
    preg_match_all('#/Subtype /Image(.*?)stream#s', $pdf, $matches);

    return array_map(fn (string $dict): array => [
        'width' => (int) (preg_match('#/Width (\d+)#', $dict, $w) ? $w[1] : 0),
        'height' => (int) (preg_match('#/Height (\d+)#', $dict, $h) ? $h[1] : 0),
        'colorspace' => preg_match('#/ColorSpace /(\w+)#', $dict, $c) ? $c[1] : '',
        'smask' => str_contains($dict, '/SMask'),
    ], $matches[1]);
}

it('aplana un PNG con transparencia a RGB opaco sobre blanco', function (): void {
    $png = PdfOpaqueImage::flatten(transparentPng());

    expect($png)->not->toBeNull()
        ->and(PdfOpaqueImage::pngColorType($png))->toBe(2)
        ->and(PdfOpaqueImage::pngHasTransparencyChunk($png))->toBeFalse()
        ->and(getimagesizefromstring($png)[0])->toBe(40)
        ->and(pixelAt($png, 30, 10))->toBe([255, 255, 255])
        ->and(pixelAt($png, 3, 10))->toBe([0, 173, 239]);
});

it('respeta el color de fondo pedido', function (): void {
    expect(pixelAt(PdfOpaqueImage::flatten(transparentPng(), '#052F60'), 30, 10))->toBe([5, 47, 96]);
});

it('también aplana PNG de paleta con color transparente', function (): void {
    $image = imagecreate(10, 10);
    $transparent = imagecolorallocate($image, 1, 2, 3);
    imagecolortransparent($image, $transparent);
    ob_start();
    imagepng($image);
    $png = PdfOpaqueImage::flatten((string) ob_get_clean());

    expect(PdfOpaqueImage::pngColorType($png))->toBe(2)
        ->and(PdfOpaqueImage::pngHasTransparencyChunk($png))->toBeFalse();
});

it('el logo de los PDF queda opaco', function (): void {
    $uri = PdfOpaqueImage::fromPath(public_path('image/logoNewPdf.png'));
    $binary = base64_decode(substr($uri, strpos($uri, ',') + 1));

    expect(PdfOpaqueImage::pngColorType((string) file_get_contents(public_path('image/logoNewPdf.png'))))->toBe(6)
        ->and($uri)->toStartWith('data:image/png;base64,')
        ->and(PdfOpaqueImage::pngColorType($binary))->toBe(2)
        ->and(getimagesizefromstring($binary)[0])->toBe(1678);
});

it('no inventa imágenes: archivo inexistente queda vacío y un data URI ilegible se devuelve igual', function (): void {
    expect(PdfOpaqueImage::fromPath('/no/existe.png'))->toBe('')
        ->and(PdfOpaqueImage::fromDataUri('data:image/png;base64,abc'))->toBe('data:image/png;base64,abc')
        ->and(PdfOpaqueImage::flatten('no es una imagen'))->toBeNull();
});

it('el sello del médico sale opaco aunque se haya subido con transparencia', function (): void {
    $uri = TelemedicineDoctorStamp::dataUri('data:image/png;base64,'.base64_encode(transparentPng()));
    $binary = base64_decode(substr($uri, strpos($uri, ',') + 1));

    expect(PdfOpaqueImage::pngColorType($binary))->toBe(2);
});

it('ningún documento de telemedicina incrusta imágenes con transparencia', function (string $view): void {
    $stamp = 'data:image/png;base64,'.base64_encode(transparentPng(300, 240));

    $data = [
        'fecha' => '22/09/2026',
        'code_reference' => 'REF-12234',
        'name_patient' => 'PACIENTE DE PRUEBA',
        'ci_patient' => '11508018',
        'age_patient' => '53',
        'doctor_name' => 'LOREN PERDOMO',
        'code_mpps' => '156692',
        'code_cm' => '1234',
        'signature' => $stamp,
        'medicationsArr' => [['medicines' => 'TOBRASOL 0.3 UNG OFT', 'indications' => 'APLICAR CADA 8 HORAS']],
        'laboratoriesArr' => [['laboratory' => 'HEMATOLOGÍA COMPLETA']],
        'studiesArr' => [['study' => 'RX DE TÓRAX']],
        'specialistsArr' => [['specialty' => 'CARDIOLOGÍA']],
        'reason_consultation' => 'CONTROL',
        'background' => 'SIN ANTECEDENTES',
        'diagnostic_impression' => 'SANO',
    ];

    $isInforme = str_contains($view, 'informe');

    if ($isInforme) {
        $data['labsArr'] = ['HEMATOLOGÍA COMPLETA'];
        $data['studiesArr'] = ['RX DE TÓRAX'];
    }

    $html = view($view, ['data' => $data])->render();
    $dompdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->getDomPDF();
    $dompdf->render();

    /** El informe estampa la firma sobre el canvas: esa ruta también debe quedar opaca. */
    $stampPath = $isInforme ? TelemedicineInformeSignatureStamp::applyTo($dompdf, $data) : null;
    $pdf = (string) $dompdf->output();
    TelemedicineInformeSignatureStamp::cleanUp($stampPath);
    $images = pdfImages($pdf);
    $logos = array_values(array_filter($images, fn (array $image): bool => $image['width'] === 1678));

    expect($logos)->not->toBeEmpty()
        ->and(array_filter($images, fn (array $image): bool => $image['smask']))->toBe([])
        ->and(array_filter($images, fn (array $image): bool => $image['colorspace'] === 'DeviceGray'))->toBe([]);

    foreach ($logos as $logo) {
        expect($logo['colorspace'])->toBe('DeviceRGB');
    }
})->with([
    'medicamentos' => ['documents.medicamentos'],
    'laboratorios' => ['documents.laboratorios'],
    'especialista' => ['documents.especialista'],
    'imagenología' => ['documents.imagenologia'],
    'informe corto' => ['documents.informe-medico-corto'],
    'informe largo' => ['documents.informe-medico-largo'],
    'informe de seguimiento' => ['documents.informe-seguimiento'],
]);
