<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineCoverageSplitPdfWriter;
use App\Support\Telemedicine\TelemedicineInformePdfRenderer;
use App\Support\Telemedicine\TelemedicineInformeSignatureStamp;
use App\Support\Telemedicine\TelemedicinePdfPageNumbers;
use Barryvdh\DomPDF\Facade\Pdf;
use setasign\Fpdi\Fpdi;

uses(Tests\TestCase::class);

/**
 * Todo documento de telemedicina de varias páginas lleva en CADA hoja la firma
 * del médico, el pie de la empresa y el folio «Página X de Y»; y ninguna hoja
 * puede quedar solo con la firma.
 *
 * Se verifica por el pipeline real (render → PDF → PNG) midiendo tinta por
 * bandas: un assert sobre el HTML no ve dónde cae cada cosa en la página.
 */
function everyPageStampDataUri(): string
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
 * @return array<string, mixed>
 */
function everyPageBaseData(): array
{
    return [
        'fecha' => '19/10/2026',
        'code_reference' => 'REF-PAGINAS',
        'name_patient' => 'PACIENTE DE PRUEBA',
        'ci_patient' => '12345678',
        'age_patient' => '40',
        'doctor_name' => 'MÉDICO DE PRUEBA',
        'code_mpps' => '123456',
        'signature' => everyPageStampDataUri(),
    ];
}

function everyPageSkipWithoutSips(): void
{
    if (trim((string) shell_exec('command -v sips')) === '') {
        test()->markTestSkipped('sips no está disponible en este entorno.');
    }
}

function everyPageWrite(string $binary): string
{
    $path = tempnam(sys_get_temp_dir(), 'tdg-paginas-').'.pdf';
    file_put_contents($path, $binary);

    return $path;
}

function everyPageCount(string $pdfPath): int
{
    return (int) (new Fpdi)->setSourceFile($pdfPath);
}

/**
 * Rasteriza una página y la devuelve compuesta sobre blanco, con su tamaño en mm.
 *
 * @return array{0: GdImage, 1: float, 2: float}
 */
function everyPageRaster(string $pdfPath, int $page): array
{
    $single = new Fpdi;
    $single->setSourceFile($pdfPath);
    $template = $single->importPage($page);
    $size = $single->getTemplateSize($template);
    $single->AddPage($size['orientation'], [$size['width'], $size['height']]);
    $single->useTemplate($template);

    $singlePath = tempnam(sys_get_temp_dir(), 'tdg-pagina-').'.pdf';
    $pngPath = $singlePath.'.png';
    $single->Output('F', $singlePath);
    exec('sips -s format png --out '.escapeshellarg($pngPath).' '.escapeshellarg($singlePath).' 2>/dev/null');

    $source = imagecreatefrompng($pngPath);
    $canvas = imagecreatetruecolor(imagesx($source), imagesy($source));
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagealphablending($canvas, true);
    imagecopy($canvas, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
    imagedestroy($source);
    @unlink($singlePath);
    @unlink($pngPath);

    return [$canvas, (float) $size['width'], (float) $size['height']];
}

/**
 * Píxeles con tinta dentro de un rectángulo de la página, en milímetros.
 *
 * @param  array{0: GdImage, 1: float, 2: float}  $raster
 */
function everyPageInk(array $raster, float $x0, float $x1, float $y0, float $y1): int
{
    [$canvas, $widthMm, $heightMm] = $raster;
    $width = imagesx($canvas);
    $height = imagesy($canvas);
    // `sips` enmarca la página: se ignora el perímetro.
    $pad = max(6, (int) round($width * 0.012));
    $ink = 0;

    $fromY = max($pad, (int) floor($height * $y0 / $heightMm));
    $toY = min($height - $pad, (int) floor($height * $y1 / $heightMm));
    $fromX = max($pad, (int) floor($width * $x0 / $widthMm));
    $toX = min($width - $pad, (int) floor($width * $x1 / $widthMm));

    for ($y = $fromY; $y < $toY; $y++) {
        for ($x = $fromX; $x < $toX; $x++) {
            $rgb = imagecolorat($canvas, $x, $y);
            // Umbral holgado: descarta la marca de agua y el antialias.
            if ((($rgb >> 16) & 0xFF) < 200 || (($rgb >> 8) & 0xFF) < 200 || ($rgb & 0xFF) < 200) {
                $ink++;
            }
        }
    }

    return $ink;
}

/**
 * @return list<string>
 */
function everyPageItems(string $prefix, int $count): array
{
    return array_map(static fn (int $i): string => "{$prefix} {$i}", range(1, $count));
}

it('el folio dice «Página X de Y»', function (): void {
    expect(TelemedicinePdfPageNumbers::label(2, 5))->toBe('Página 2 de 5')
        ->and(TelemedicinePdfPageNumbers::label(1, 1))->toBe('Página 1 de 1');
});

it('el folio queda debajo del pie y dentro de la hoja', function (): void {
    expect(TelemedicinePdfPageNumbers::PORTRAIT_TOP_MM)->toBeGreaterThan(279.0)->toBeLessThan(290.0)
        ->and(TelemedicinePdfPageNumbers::RECIPE_TOP_MM)->toBeGreaterThan(198.0)->toBeLessThan(205.0);
});

it('todos los caminos de producción foliar el documento', function (): void {
    $root = dirname(__DIR__, 2);

    expect(file_get_contents($root.'/app/Support/Telemedicine/TelemedicineInformePdfRenderer.php'))
        ->toContain('TelemedicinePdfPageNumbers::applyToPortrait($dompdf)')
        ->and(file_get_contents($root.'/app/Support/Telemedicine/TelemedicineCoverageSplitPdfWriter.php'))
        ->toContain('TelemedicinePdfPageNumbers::applyToRecipe($dompdf)')
        ->toContain('TelemedicinePdfPageNumbers::applyToPortrait($dompdf)')
        ->toContain('self::render($view, $orientation, $payload)')
        ->and(file_get_contents($root.'/app/Services/TelemedicineCaseBitacoraPdfService.php'))
        ->toContain('TelemedicinePdfPageNumbers::applyToPortrait($dompdf)');
});

it('las órdenes llevan firma, pie y folio en cada página y ninguna queda solo con la firma', function (string $view, string $key, int $count): void {
    everyPageSkipWithoutSips();

    $path = everyPageWrite(TelemedicineCoverageSplitPdfWriter::render(
        $view,
        'portrait',
        everyPageBaseData() + [$key => everyPageItems('ÍTEM', $count)],
    ));
    $pages = everyPageCount($path);

    $signatureTop = TelemedicineInformeSignatureStamp::PAGE_HEIGHT_MM - TelemedicineInformeSignatureStamp::bottomMarginMm();
    $footerTop = TelemedicineInformeSignatureStamp::PAGE_HEIGHT_MM - TelemedicineInformeSignatureStamp::FOOTER_BAND_MM;

    for ($page = 1; $page <= $pages; $page++) {
        $raster = everyPageRaster($path, $page);
        $label = "{$view} con {$count} ítems, página {$page} de {$pages}";

        expect(everyPageInk($raster, 20, 190, 15, $signatureTop - 1))->toBeGreaterThan(0, "{$label}: la hoja quedó solo con firma y pie.")
            ->and(everyPageInk($raster, 20, 190, $signatureTop, $footerTop))->toBeGreaterThan(0, "{$label}: falta la firma.")
            ->and(everyPageInk($raster, 20, 190, $footerTop, 279))->toBeGreaterThan(0, "{$label}: falta el pie.")
            ->and(everyPageInk($raster, 80, 130, 280.5, 287))->toBeGreaterThan(0, "{$label}: falta el folio.");

        imagedestroy($raster[0]);
    }

    @unlink($path);

    if ($count >= 60) {
        expect($pages)->toBeGreaterThan(1);
    }
})->with([
    'laboratorios cortos' => ['documents.laboratorios', 'labsArr', 3],
    'laboratorios en el límite de la hoja' => ['documents.laboratorios', 'labsArr', 23],
    'laboratorios justo después del límite' => ['documents.laboratorios', 'labsArr', 24],
    'laboratorios largos' => ['documents.laboratorios', 'labsArr', 60],
    'imagenología larga' => ['documents.imagenologia', 'studiesArr', 60],
    'especialistas largos' => ['documents.especialista', 'consultSpecialistArr', 60],
]);

it('la lista de la orden empieza en la primera página aunque no quepa entera', function (): void {
    everyPageSkipWithoutSips();

    $path = everyPageWrite(TelemedicineCoverageSplitPdfWriter::render(
        'documents.laboratorios',
        'portrait',
        everyPageBaseData() + ['labsArr' => everyPageItems('LABORATORIO', 60)],
    ));
    $raster = everyPageRaster($path, 1);

    // Debajo de los datos del paciente (≈ 60 mm) ya tiene que haber filas: antes la
    // lista entera saltaba a la página 2 y la primera quedaba casi vacía.
    expect(everyPageInk($raster, 20, 190, 90, 200))->toBeGreaterThan(0);

    imagedestroy($raster[0]);
    @unlink($path);
});

it('el récipe lleva firma, pie y folio en cada mitad de cada página, con original y copia a la par', function (int $count): void {
    everyPageSkipWithoutSips();

    $medications = array_map(
        static fn (string $name): array => ['medicines' => $name, 'indications' => 'TOMAR UNA CADA 8 HORAS POR 5 DÍAS CON ABUNDANTE AGUA'],
        everyPageItems('MEDICAMENTO', $count),
    );
    $path = everyPageWrite(TelemedicineCoverageSplitPdfWriter::render(
        'documents.medicamentos',
        'landscape',
        everyPageBaseData() + ['medicationsArr' => $medications],
    ));
    $pages = everyPageCount($path);

    // Mitad original (izquierda) y copia (derecha) de la hoja horizontal.
    $halves = ['original' => [14.0, 142.0], 'copia' => [155.0, 283.0]];

    for ($page = 1; $page <= $pages; $page++) {
        $raster = everyPageRaster($path, $page);

        foreach ($halves as $half => [$x0, $x1]) {
            $label = "Récipe con {$count} medicamentos, {$half}, página {$page} de {$pages}";

            expect(everyPageInk($raster, $x0, $x1, 12, 153))->toBeGreaterThan(0, "{$label}: la mitad quedó sin contenido.")
                ->and(everyPageInk($raster, $x0, $x1, 154, 180))->toBeGreaterThan(0, "{$label}: falta la firma.")
                ->and(everyPageInk($raster, $x0, $x1, 181, 198))->toBeGreaterThan(0, "{$label}: falta el pie.")
                ->and(everyPageInk($raster, $x0 + 40, $x1 - 40, 199.5, 205))->toBeGreaterThan(0, "{$label}: falta el folio.");
        }

        imagedestroy($raster[0]);
    }

    @unlink($path);

    if ($count >= 20) {
        expect($pages)->toBeGreaterThan(1);
    }
})->with([
    'corto' => [3],
    'una hoja llena' => [9],
    'dos hojas' => [10],
    'varias hojas' => [30],
]);

it('el informe médico lleva el folio en cada página', function (): void {
    everyPageSkipWithoutSips();

    $medications = array_map(
        static fn (string $name): array => ['medicines' => $name, 'indications' => 'INDICACIÓN'],
        everyPageItems('MEDICAMENTO', 45),
    );
    $path = everyPageWrite(TelemedicineInformePdfRenderer::render(
        TelemedicineInformePdfRenderer::VIEW_LARGO,
        everyPageBaseData() + ['reason' => 'DOLOR', 'actual_phatology' => 'CERVICALGIA', 'medicationsArr' => $medications],
    ));
    $pages = everyPageCount($path);

    expect($pages)->toBeGreaterThan(1);

    for ($page = 1; $page <= $pages; $page++) {
        $raster = everyPageRaster($path, $page);

        expect(everyPageInk($raster, 80, 130, 280.5, 287))->toBeGreaterThan(0, "Informe, página {$page} de {$pages}: falta el folio.");

        imagedestroy($raster[0]);
    }

    @unlink($path);
});

it('la bitácora repite encabezado, pie y folio en cada página', function (): void {
    everyPageSkipWithoutSips();

    $entries = array_map(
        static fn (string $note): array => ['Fecha' => '19/10/2026', 'Detalle' => $note.' '.str_repeat('TEXTO DE SEGUIMIENTO DEL CASO. ', 8)],
        everyPageItems('NOTA', 25),
    );
    $dompdf = Pdf::loadView('documents.bitacora-caso', ['dossier' => [
        'code' => 'TLM-PAGINAS',
        'status' => 'EN SEGUIMIENTO',
        'patient' => ['Nombre' => 'PACIENTE DE PRUEBA'],
        'header' => ['Código' => 'TLM-PAGINAS'],
        'follow_ups' => $entries,
    ]])->setPaper('a4', 'portrait')->getDomPDF();
    $dompdf->render();
    TelemedicinePdfPageNumbers::applyToPortrait($dompdf);

    $path = everyPageWrite((string) $dompdf->output());
    $pages = everyPageCount($path);

    expect($pages)->toBeGreaterThan(1);

    for ($page = 1; $page <= $pages; $page++) {
        $raster = everyPageRaster($path, $page);
        $label = "Bitácora, página {$page} de {$pages}";

        // El encabezado ocupa todo el ancho: se mira su lado derecho (título y código).
        expect(everyPageInk($raster, 120, 190, 12, 40))->toBeGreaterThan(0, "{$label}: falta el encabezado.")
            ->and(everyPageInk($raster, 20, 190, 262, 279))->toBeGreaterThan(0, "{$label}: falta el pie.")
            ->and(everyPageInk($raster, 80, 130, 280.5, 287))->toBeGreaterThan(0, "{$label}: falta el folio.");

        imagedestroy($raster[0]);
    }

    @unlink($path);
});
