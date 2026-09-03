<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineInformePdfRenderer;
use App\Support\Telemedicine\TelemedicineInformeSignatureStamp;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;

uses(Tests\TestCase::class);

/**
 * La firma estuvo primero fija a `top: 222mm` —se repetía en todas las páginas y
 * pisaba el texto— y después en el flujo del documento, donde ocupaba ~34 mm y,
 * cuando no cabían, saltaba sola a una hoja vacía: el 31 % de los informes.
 *
 * Hoy se dibuja sobre el canvas, fuera del flujo, dentro de la banda que el
 * margen inferior reserva en todas las páginas. Estas pruebas fijan las dos
 * garantías que se derivan de eso: la firma no puede crear una página, y no
 * puede invadir ni el texto ni el pie.
 */
function informeMedicoPartialSource(): string
{
    return (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/views/documents/partials/informe-medico-homologado.blade.php'
    );
}

function informeMedicoStampDataUri(): string
{
    $image = imagecreatetruecolor(400, 200);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    $black = imagecolorallocate($image, 0, 0, 0);
    imagesetthickness($image, 10);
    imageline($image, 30, 160, 150, 40, $black);
    imageline($image, 150, 40, 250, 150, $black);

    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    return 'data:image/png;base64,'.base64_encode($png);
}

/**
 * @param  int  $repetitions  Cuántas veces se repite el párrafo clínico.
 * @return array<string, mixed>
 */
function informeMedicoSignatureSampleData(int $repetitions = 1): array
{
    $paragraph = 'PACIENTE MASCULINO DE 41 AÑOS SIN ANTECEDENTES PATOLÓGICOS QUE ACUDE POR CERVICALGIA DE VARIOS MESES. ';

    return [
        'fecha' => '02/09/2026',
        'code_reference' => 'REF-32112',
        'name_patient' => 'ALBERT LEOMAR VILLASMIL PAEZ',
        'ci_patient' => '18259674',
        'age_patient' => '41',
        'reason' => 'DOLOR CERVICAL',
        'actual_phatology' => str_repeat($paragraph, $repetitions),
        'background' => '',
        'diagnostic_impression' => '1) CERVICALGIA CRÓNICA MECÁNICA',
        'peso' => '86',
        'estatura' => '1.75',
        'imc' => '28',
        'pa' => '120/80',
        'fc' => '80',
        'fr' => '18',
        'temp' => '37',
        'saturacion' => '98',
        'medicationsArr' => [['medicines' => 'ACETAMINOFEN', 'indications' => 'INDICACION']],
        'labsArr' => ['CREATININA', 'ANTIGENO PROSTATICO'],
        'studiesArr' => ['ECO ABDOMINAL'],
        'doctor_name' => 'JENNIFER CAROLINA CASTRO PEREIRA',
        'code_mpps' => '162641',
        'signature' => informeMedicoStampDataUri(),
    ];
}

function informeMedicoViewFor(string $variant): string
{
    return $variant === 'largo'
        ? TelemedicineInformePdfRenderer::VIEW_LARGO
        : TelemedicineInformePdfRenderer::VIEW_CORTO;
}

function informeMedicoSkipWithoutSips(): void
{
    if (trim((string) shell_exec('command -v sips')) === '') {
        test()->markTestSkipped('sips no está disponible en este entorno.');
    }
}

/**
 * Píxeles con tinta en una franja horizontal de una página, medida en milímetros.
 */
function informeMedicoInkBetween(string $pdfPath, int $page, float $fromMm, float $toMm): int
{
    $single = new Fpdi;
    $single->setSourceFile($pdfPath);
    $template = $single->importPage($page);
    $size = $single->getTemplateSize($template);
    $single->AddPage($size['orientation'], [$size['width'], $size['height']]);
    $single->useTemplate($template);

    $singlePath = tempnam(sys_get_temp_dir(), 'informe-band-').'.pdf';
    $pngPath = $singlePath.'.png';
    $single->Output('F', $singlePath);
    exec('sips -s format png --out '.escapeshellarg($pngPath).' '.escapeshellarg($singlePath).' 2>/dev/null');

    $source = imagecreatefrompng($pngPath);
    $width = imagesx($source);
    $height = imagesy($source);

    // El PNG conserva alfa: sin componer sobre blanco lo transparente se lee negro.
    $canvas = imagecreatetruecolor($width, $height);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagealphablending($canvas, true);
    imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
    imagedestroy($source);

    // `sips` enmarca la página: se ignora el perímetro.
    $pad = max(6, (int) round($width * 0.012));
    $pageHeight = TelemedicineInformeSignatureStamp::PAGE_HEIGHT_MM;
    $first = (int) floor($height * ($fromMm / $pageHeight));
    $last = (int) floor($height * ($toMm / $pageHeight));
    $ink = 0;

    for ($y = max($first, $pad); $y < min($last, $height - $pad); $y++) {
        for ($x = $pad; $x < $width - $pad; $x++) {
            $rgb = imagecolorat($canvas, $x, $y);
            // Umbral holgado: descarta la marca de agua (opacidad 0.052) y el antialias.
            if ((($rgb >> 16) & 0xFF) < 200 || (($rgb >> 8) & 0xFF) < 200 || ($rgb & 0xFF) < 200) {
                $ink++;
            }
        }
    }

    imagedestroy($canvas);
    @unlink($singlePath);
    @unlink($pngPath);

    return $ink;
}

it('la plantilla ya no maqueta la firma: la estampa el canvas', function (): void {
    expect(informeMedicoPartialSource())
        ->not->toContain('doctor-signature')
        ->not->toContain('top: 222mm')
        ->toContain('TelemedicineInformeSignatureStamp::bottomMarginMm()');
});

it('el pie sigue fijo porque debe repetirse en cada página', function (): void {
    expect(informeMedicoPartialSource())
        ->toMatch('/\.footer-fixed\s*\{[^}]*position:\s*fixed/s');
});

it('el margen inferior reserva las bandas del pie y de la firma', function (): void {
    expect(TelemedicineInformeSignatureStamp::bottomMarginMm())
        ->toBe(TelemedicineInformeSignatureStamp::FOOTER_BAND_MM + TelemedicineInformeSignatureStamp::SIGNATURE_BAND_MM);
});

it('la firma solo se dibuja en la última página', function (): void {
    $source = (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineInformeSignatureStamp.php'
    );

    expect($source)
        ->toContain('page_script')
        ->toContain('if ($pageNumber !== $pageCount)');
});

it('ambos informes se generan por el mismo renderizador', function (): void {
    $corto = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/GeneratePdfInformeMedicoCorto.php');
    $largo = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineInformeLargoPdfGenerator.php');

    expect($corto)->toContain('TelemedicineInformePdfRenderer::save')
        ->and($largo)->toContain('TelemedicineInformePdfRenderer::save');
});

it('estampa la firma y nunca la deja sola en la última página', function (string $variant, int $repetitions): void {
    informeMedicoSkipWithoutSips();

    $path = tempnam(sys_get_temp_dir(), 'informe-orphan-').'.pdf';
    file_put_contents($path, TelemedicineInformePdfRenderer::render(
        informeMedicoViewFor($variant),
        informeMedicoSignatureSampleData($repetitions),
    ));

    $fpdi = new Fpdi;
    $pages = (int) $fpdi->setSourceFile($path);

    $pageHeight = TelemedicineInformeSignatureStamp::PAGE_HEIGHT_MM;
    $signatureBandTop = $pageHeight - TelemedicineInformeSignatureStamp::bottomMarginMm();
    $footerBandTop = $pageHeight - TelemedicineInformeSignatureStamp::FOOTER_BAND_MM;

    $contenido = informeMedicoInkBetween($path, $pages, 20.0, $signatureBandTop);
    $firma = informeMedicoInkBetween($path, $pages, $signatureBandTop, $footerBandTop);

    @unlink($path);

    expect($firma)->toBeGreaterThan(0, "El informe {$variant} (x{$repetitions}) no estampó la firma en la última página.");

    if ($pages > 1) {
        expect($contenido)->toBeGreaterThan(0, "El informe {$variant} (x{$repetitions}) dejó la última página solo con la firma.");
    }
})->with([
    ['corto', 9],
    ['corto', 13],
    ['largo', 5],
    ['largo', 30],
]);

it('ni el informe ni la firma invaden la banda del pie', function (string $variant, int $repetitions): void {
    informeMedicoSkipWithoutSips();

    $data = informeMedicoSignatureSampleData($repetitions);
    $html = view(informeMedicoViewFor($variant), ['data' => $data])->render();
    // Se oculta el pie: si su banda queda limpia, nada la invade.
    $html = str_replace('</head>', '<style>.footer-fixed{display:none !important;}</style></head>', $html);

    $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
    $dompdf = $pdf->getDomPDF();
    $dompdf->render();
    TelemedicineInformeSignatureStamp::applyTo($dompdf, $data);

    $path = tempnam(sys_get_temp_dir(), 'informe-footer-').'.pdf';
    file_put_contents($path, (string) $dompdf->output());

    $fpdi = new Fpdi;
    $pages = (int) $fpdi->setSourceFile($path);
    $footerBandTop = TelemedicineInformeSignatureStamp::PAGE_HEIGHT_MM
        - TelemedicineInformeSignatureStamp::FOOTER_BAND_MM;

    for ($page = 1; $page <= $pages; $page++) {
        $ink = informeMedicoInkBetween($path, $page, $footerBandTop, TelemedicineInformeSignatureStamp::PAGE_HEIGHT_MM);

        expect($ink)->toBe(0, sprintf(
            'El informe %s (x%d) pinta %d píxeles sobre la banda del pie en la página %d.',
            $variant, $repetitions, $ink, $page
        ));
    }

    @unlink($path);
})->with([
    ['corto', 9],
    ['corto', 13],
    ['largo', 30],
]);
