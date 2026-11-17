<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Logo que el analista carga para la página de cálculos de la cotización.
 *
 * El logo cargado tiene su propia caja en el encabezado: hasta 120 × 40 pt
 * (`PDF_WIDTH_PT`), la misma altura del bloque de título de la derecha, así
 * que el encabezado no crece. Se acomoda en un lienzo transparente 3:1,
 * alineado a la izquierda y centrado en vertical: un logo redondo queda de
 * 40 × 40 pt y uno alargado llega hasta 120 pt de ancho. El logo de
 * Tu Doctor en Casa, cuando no hay uno cargado, conserva su tamaño de siempre.
 *
 * Cualquier problema (archivo borrado, formato no soportado, imagen dañada)
 * devuelve null y el PDF sale con el logo de siempre.
 */
final class PlanGeneratorLogo
{
    public const DIRECTORY = 'plan-generator-logos';

    public const MAX_KILOBYTES = 5120;

    /**
     * @var list<string>
     */
    public const ACCEPTED_TYPES = ['image/png', 'image/jpeg'];

    /**
     * Ancho con que el PDF dibuja el logo cargado; el alto sale del lienzo 3:1 (40 pt, el alto del bloque de título).
     */
    public const PDF_WIDTH_PT = 120;

    private const CANVAS_WIDTH = 1200;

    private const CANVAS_HEIGHT = 400;

    private const CACHE_PREFIX = 'plan_generator_custom_logo_uri:v2:';

    private const CACHE_SECONDS = 60 * 60 * 24 * 30;

    /**
     * Data URI PNG listo para DomPDF, o null si no hay logo propio utilizable.
     */
    public static function dataUri(?string $relativePath): ?string
    {
        $absolutePath = self::absolutePath($relativePath);

        if ($absolutePath === null) {
            return null;
        }

        $key = self::CACHE_PREFIX.sha1($absolutePath.'|'.(int) @filemtime($absolutePath).'|'.(int) @filesize($absolutePath));

        $uri = Cache::remember($key, self::CACHE_SECONDS, static fn (): string => self::render($absolutePath) ?? '');

        return $uri !== '' ? $uri : null;
    }

    /**
     * Huella del logo para la versión de caché del PDF: cambia si se reemplaza el archivo.
     */
    public static function fingerprint(?string $relativePath): string
    {
        $absolutePath = self::absolutePath($relativePath);

        if ($absolutePath === null) {
            return '';
        }

        return (string) $relativePath.'@'.(int) @filemtime($absolutePath).'@'.(int) @filesize($absolutePath);
    }

    /**
     * Ruta absoluta en el disco público, solo dentro del directorio de logos.
     */
    public static function absolutePath(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);

        if ($relativePath === ''
            || ! str_starts_with($relativePath, self::DIRECTORY.'/')
            || str_contains($relativePath, '..')
            || str_contains($relativePath, "\0")) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($relativePath);

        return is_file($absolutePath) ? $absolutePath : null;
    }

    private static function render(string $absolutePath): ?string
    {
        try {
            $info = @getimagesize($absolutePath);

            if ($info === false || ! in_array($info[2] ?? null, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
                return null;
            }

            [$width, $height] = [(int) $info[0], (int) $info[1]];

            if ($width < 1 || $height < 1 || $width * $height > 40_000_000) {
                return null;
            }

            $source = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($absolutePath) : @imagecreatefromjpeg($absolutePath);

            if ($source === false) {
                return null;
            }

            $canvas = imagecreatetruecolor(self::CANVAS_WIDTH, self::CANVAS_HEIGHT);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
            imagealphablending($canvas, true);

            $scale = min(self::CANVAS_WIDTH / $width, self::CANVAS_HEIGHT / $height);
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $offsetY = (int) floor((self::CANVAS_HEIGHT - $targetHeight) / 2);

            imagecopyresampled($canvas, $source, 0, $offsetY, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($source);

            ob_start();
            imagepng($canvas, null, 6);
            $png = (string) ob_get_clean();
            imagedestroy($canvas);

            return $png !== '' ? 'data:image/png;base64,'.base64_encode($png) : null;
        } catch (Throwable $exception) {
            Log::warning('No se pudo preparar el logo de la cotización del generador de planes.', [
                'path' => $absolutePath,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
