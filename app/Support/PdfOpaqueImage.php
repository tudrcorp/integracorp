<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Convierte una imagen con transparencia en una opaca, aplanada sobre un color
 * de fondo, antes de entregarla a DomPDF.
 *
 * Por qué: DomPDF no incrusta PNG con canal alfa tal cual. Los parte en dos
 * imágenes (máscara gris + color) usando archivos temporales y Imagick o GD, y
 * si ese paso falla en el servidor lo hace en silencio: el PDF queda con la
 * máscara dibujada en lugar del logo (letras blancas sobre fondo negro, y la
 * marca de agua como un rectángulo gris). Pasó con los documentos de
 * telemedicina aunque en local salían bien.
 *
 * Una imagen sin alfa (PNG RGB, tipo de color 2) nunca entra en esa ruta: DomPDF
 * la incrusta directo. Así el resultado deja de depender de Imagick, de la
 * carpeta temporal o del worker que genere el documento.
 */
final class PdfOpaqueImage
{
    public const DEFAULT_BACKGROUND = '#FFFFFF';

    private const MAX_CACHE_ENTRIES = 32;

    /**
     * @var array<string, string>
     */
    private static array $cache = [];

    /**
     * Data URI opaco de un archivo, o '' si no existe o no es una imagen legible.
     */
    public static function fromPath(string $absolutePath, string $background = self::DEFAULT_BACKGROUND): string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return '';
        }

        $key = 'path:'.$absolutePath.'|'.(string) @filemtime($absolutePath).'|'.(string) @filesize($absolutePath).'|'.$background;

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $binary = @file_get_contents($absolutePath);

        if ($binary === false || $binary === '') {
            return '';
        }

        $png = self::flatten($binary, $background);

        if ($png === null) {
            self::warn('PdfOpaqueImage: no se pudo aplanar la imagen para el PDF', ['path' => $absolutePath]);

            return '';
        }

        return self::remember($key, 'data:image/png;base64,'.base64_encode($png));
    }

    /**
     * Data URI opaco a partir de otro data URI. Si el contenido no es una imagen
     * que GD pueda leer, se devuelve tal cual: no se inventa una imagen.
     */
    public static function fromDataUri(string $dataUri, string $background = self::DEFAULT_BACKGROUND): string
    {
        $dataUri = trim($dataUri);

        if (! str_starts_with($dataUri, 'data:image')) {
            return $dataUri;
        }

        $comma = strpos($dataUri, ',');

        if ($comma === false || ! str_contains(substr($dataUri, 0, $comma), ';base64')) {
            return $dataUri;
        }

        $binary = base64_decode(substr($dataUri, $comma + 1), true);

        if ($binary === false || $binary === '') {
            return $dataUri;
        }

        $key = 'uri:'.hash('sha256', $binary).'|'.$background;

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $png = self::flatten($binary, $background);

        return self::remember($key, $png === null ? $dataUri : 'data:image/png;base64,'.base64_encode($png));
    }

    /**
     * PNG opaco (RGB, sin canal alfa ni color transparente) o null si GD no
     * puede leer la imagen.
     */
    public static function flatten(string $binary, string $background = self::DEFAULT_BACKGROUND): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $source = @imagecreatefromstring($binary);

            if ($source === false) {
                return null;
            }

            $width = imagesx($source);
            $height = imagesy($source);

            if ($width < 1 || $height < 1) {
                return null;
            }

            [$red, $green, $blue] = self::rgb($background);

            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, true);
            imagefilledrectangle($canvas, 0, 0, $width - 1, $height - 1, imagecolorallocate($canvas, $red, $green, $blue));

            if (! imageistruecolor($source)) {
                imagepalettetotruecolor($source);
            }

            imagealphablending($source, true);
            imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
            imagesavealpha($canvas, false);

            ob_start();
            $written = imagepng($canvas, null, 6);
            $png = (string) ob_get_clean();

            return $written && $png !== '' ? $png : null;
        } catch (Throwable $exception) {
            self::warn('PdfOpaqueImage: error aplanando imagen', ['message' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Tipo de color PNG del binario (2 = RGB opaco, 6 = RGBA), o null si no es PNG.
     */
    public static function pngColorType(string $binary): ?int
    {
        if (strlen($binary) < 26 || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        return ord($binary[25]);
    }

    /**
     * Si el PNG declara un color transparente (chunk tRNS), DomPDF también lo trataría aparte.
     */
    public static function pngHasTransparencyChunk(string $binary): bool
    {
        return str_contains($binary, 'tRNS');
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return [255, 255, 255];
        }

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    /**
     * Registrar nunca debe romper la generación del PDF, ni siquiera sin contenedor.
     *
     * @param  array<string, mixed>  $context
     */
    private static function warn(string $message, array $context): void
    {
        try {
            Log::warning($message, $context);
        } catch (Throwable) {
            error_log($message.' '.json_encode($context));
        }
    }

    private static function remember(string $key, string $value): string
    {
        if (count(self::$cache) >= self::MAX_CACHE_ENTRIES) {
            array_shift(self::$cache);
        }

        return self::$cache[$key] = $value;
    }
}
