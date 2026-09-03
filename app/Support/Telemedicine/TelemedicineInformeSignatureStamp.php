<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Dompdf\Dompdf;

/**
 * Estampa la firma del médico en la ÚLTIMA página del informe.
 *
 * La firma no puede ir en el flujo del documento. Ocupa unos 34 mm y, cuando en
 * la última página quedaba menos que eso, saltaba entera y se llevaba una hoja
 * para ella sola (medido: el 31 % de los informes). Encogerla solo desplazaba la
 * franja del fallo, no lo quitaba.
 *
 * Aquí se dibuja sobre el canvas, fuera del flujo, con dos consecuencias que son
 * garantías y no estadísticas:
 *
 *   1. La firma no puede provocar una página nueva, así que nunca queda sola: si
 *      hay una página más es porque había contenido que no cupo.
 *   2. Su banda queda reservada en todas las páginas (ver bottomMarginMm), así
 *      que nunca se pinta encima del texto ni del pie.
 */
final class TelemedicineInformeSignatureStamp
{
    public const PAGE_WIDTH_MM = 210.0;

    public const PAGE_HEIGHT_MM = 297.0;

    /** Banda inferior del pie fijo, reservada en todas las páginas. */
    public const FOOTER_BAND_MM = 35.0;

    /**
     * Banda de la firma, justo encima de la del pie.
     *
     * Ajustada al alto real del bloque (sello 17 mm + nombre y MPPS ~7,6 mm) más
     * un respiro. Reservar de más no da ninguna garantía extra y sí resta área
     * útil en todas las páginas, empujando a una hoja más los informes que están
     * en el límite.
     */
    public const SIGNATURE_BAND_MM = 27.0;

    public const SIDE_MARGIN_MM = 20.0;

    private const STAMP_WIDTH_MM = 34.0;

    private const STAMP_MAX_HEIGHT_MM = 17.0;

    /**
     * Margen inferior que debe declarar el cuerpo del documento para dejar libres
     * la banda del pie y la de la firma. La plantilla lo consume: si cambia aquí,
     * cambia allí.
     */
    public static function bottomMarginMm(): float
    {
        return self::FOOTER_BAND_MM + self::SIGNATURE_BAND_MM;
    }

    /**
     * Registra el dibujo de la firma. Debe llamarse tras `render()` y antes de
     * `output()`.
     *
     * @param  array<string, mixed>  $data
     */
    public static function applyTo(Dompdf $dompdf, array $data): void
    {
        $doctorName = trim((string) ($data['doctor_name'] ?? ''));
        $mpps = trim((string) ($data['code_mpps'] ?? ''));
        $stampPath = self::resolveStampPath($data['signature'] ?? null);

        if ($doctorName === '' && $mpps === '' && $stampPath === null) {
            return;
        }

        $canvas = $dompdf->getCanvas();

        $canvas->page_script(function (
            int $pageNumber,
            int $pageCount,
            $canvas,
            $fontMetrics
        ) use ($stampPath, $doctorName, $mpps): void {
            if ($pageNumber !== $pageCount) {
                return;
            }

            self::draw($canvas, $fontMetrics, $stampPath, $doctorName, $mpps);
        });
    }

    /**
     * Borra el archivo temporal del sello, si se creó uno. Llamar tras `output()`.
     */
    public static function cleanUp(mixed $signature): void
    {
        $path = self::temporaryPathFor($signature);

        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    public static function mmToPt(float $mm): float
    {
        return $mm * 72.0 / 25.4;
    }

    private static function draw(
        mixed $canvas,
        mixed $fontMetrics,
        ?string $stampPath,
        string $doctorName,
        string $mpps,
    ): void {
        $bandTop = self::mmToPt(self::PAGE_HEIGHT_MM - self::FOOTER_BAND_MM - self::SIGNATURE_BAND_MM);
        $centerX = self::mmToPt(self::PAGE_WIDTH_MM / 2);
        $cursorY = $bandTop;

        if ($stampPath !== null && is_file($stampPath)) {
            [$width, $height] = self::stampSize($stampPath);

            if ($width > 0 && $height > 0) {
                $canvas->image($stampPath, $centerX - $width / 2, $cursorY, $width, $height);
                $cursorY += $height + self::mmToPt(1.5);
            }
        }

        if ($doctorName !== '') {
            $font = $fontMetrics->getFont('DejaVu Sans', 'bold');
            $size = self::fitFontSize($fontMetrics, $font, $doctorName, 8.0);
            $textWidth = $fontMetrics->getTextWidth($doctorName, $font, $size);
            $canvas->text($centerX - $textWidth / 2, $cursorY, $doctorName, $font, $size, [0.07, 0.09, 0.15]);
            $cursorY += self::mmToPt(3.6);
        }

        if ($mpps !== '') {
            $label = 'MPPS: '.$mpps;
            $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $size = self::fitFontSize($fontMetrics, $font, $label, 7.0);
            $textWidth = $fontMetrics->getTextWidth($label, $font, $size);
            $canvas->text($centerX - $textWidth / 2, $cursorY, $label, $font, $size, [0.29, 0.33, 0.39]);
        }

        unset($canvas, $fontMetrics);
    }

    /**
     * Reduce el cuerpo hasta que el texto entre en el ancho útil: un nombre muy
     * largo no debe salirse de la hoja.
     */
    private static function fitFontSize(mixed $fontMetrics, mixed $font, string $text, float $size): float
    {
        $maxWidth = self::mmToPt(self::PAGE_WIDTH_MM - (self::SIDE_MARGIN_MM * 2));

        while ($size > 5.0 && $fontMetrics->getTextWidth($text, $font, $size) > $maxWidth) {
            $size -= 0.25;
        }

        return $size;
    }

    /**
     * @return array{0: float, 1: float} Ancho y alto en puntos, respetando la proporción.
     */
    private static function stampSize(string $path): array
    {
        $size = @getimagesize($path);

        if ($size === false || (int) $size[0] <= 0 || (int) $size[1] <= 0) {
            return [0.0, 0.0];
        }

        $width = self::mmToPt(self::STAMP_WIDTH_MM);
        $height = $width * ((int) $size[1] / (int) $size[0]);
        $maxHeight = self::mmToPt(self::STAMP_MAX_HEIGHT_MM);

        if ($height > $maxHeight) {
            $width *= $maxHeight / $height;
            $height = $maxHeight;
        }

        return [$width, $height];
    }

    /**
     * `Canvas::image()` necesita una ruta; el sello llega como data URI, así que
     * se vuelca a un temporal estable (mismo contenido => misma ruta).
     */
    private static function resolveStampPath(mixed $signature): ?string
    {
        $dataUri = TelemedicineDoctorStamp::dataUri($signature);

        if ($dataUri === '') {
            return null;
        }

        $comma = strpos($dataUri, ',');

        if ($comma === false) {
            return null;
        }

        $binary = base64_decode(substr($dataUri, $comma + 1), true);

        if ($binary === false || $binary === '') {
            return null;
        }

        $path = self::temporaryPathFor($signature);

        if ($path === null) {
            return null;
        }

        if (! is_file($path)) {
            file_put_contents($path, $binary);
        }

        return $path;
    }

    private static function temporaryPathFor(mixed $signature): ?string
    {
        $dataUri = TelemedicineDoctorStamp::dataUri($signature);

        if ($dataUri === '') {
            return null;
        }

        return sys_get_temp_dir().'/tdg-firma-'.md5($dataUri).'.png';
    }
}
