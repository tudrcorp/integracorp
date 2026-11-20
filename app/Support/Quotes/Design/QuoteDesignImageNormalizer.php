<?php

declare(strict_types=1);

namespace App\Support\Quotes\Design;

use App\Models\QuoteDesign;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lleva cada página fija que sube el analista a una hoja A4 vertical exacta (JPEG a 200 ppp).
 *
 * - Diseño suele enviar en tamaño carta u otra proporción: la imagen se **recorta centrada**
 *   para llenar la hoja, nunca se deforma ni deja franjas blancas.
 * - Volver a codificarla con GD descarta cualquier contenido ajeno a la imagen y deja un archivo
 *   que FPDF siempre sabe leer (un PNG entrelazado, por ejemplo, lo haría fallar).
 * - Una imagen ya normalizada (sufijo `-a4.jpg`) se deja como está: guardar dos veces no la
 *   degrada.
 */
final class QuoteDesignImageNormalizer
{
    public const WIDTH = 1654;

    public const HEIGHT = 2339;

    public const SUFFIX = '-a4.jpg';

    public const PAGES_DIRECTORY = QuoteDesign::DIRECTORY.'/pages';

    /** Más allá de esto GD necesitaría cientos de MB de memoria para abrirla. */
    private const MAX_SOURCE_PIXELS = 40_000_000;

    public static function isNormalized(string $path): bool
    {
        return str_starts_with($path, self::PAGES_DIRECTORY.'/') && str_ends_with($path, self::SUFFIX);
    }

    /**
     * Normaliza una imagen del disco `public` y devuelve la ruta de la versión A4. La original no se
     * toca aquí: {@see QuoteDesignPersistence} la borra solo si el guardado se confirma, para que
     * un fallo a mitad no deje el formulario apuntando a un archivo que ya no existe.
     *
     * @throws RuntimeException si el archivo no es una imagen PNG o JPEG legible
     */
    public static function normalize(string $path): string
    {
        if (self::isNormalized($path)) {
            return $path;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            throw new RuntimeException('No se encontró la imagen subida. Vuelva a cargarla.');
        }

        $contents = (string) $disk->get($path);
        $jpeg = self::toA4Jpeg($contents);

        $target = self::PAGES_DIRECTORY.'/'.Str::uuid()->toString().self::SUFFIX;
        $disk->put($target, $jpeg);

        return $target;
    }

    /**
     * @throws RuntimeException
     */
    public static function toA4Jpeg(string $contents): string
    {
        $info = @getimagesizefromstring($contents);

        if ($info === false || ! in_array($info[2] ?? null, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            throw new RuntimeException('La página debe ser una imagen PNG o JPG.');
        }

        [$width, $height] = $info;

        if ($width < 300 || $height < 300) {
            throw new RuntimeException('La imagen es demasiado pequeña para una hoja completa (mínimo 300 × 300 px; se recomienda 1654 × 2339 px).');
        }

        if ($width * $height > self::MAX_SOURCE_PIXELS) {
            throw new RuntimeException('La imagen es demasiado grande. Expórtela a 1654 × 2339 px (A4 a 200 ppp).');
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('No se pudo leer la imagen. Expórtela de nuevo como PNG o JPG.');
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        try {
            /** Fondo blanco: un PNG con transparencia no debe salir con zonas negras. */
            imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));

            [$cropX, $cropY, $cropWidth, $cropHeight] = self::coverCrop($width, $height);

            imagecopyresampled($canvas, $source, 0, 0, $cropX, $cropY, self::WIDTH, self::HEIGHT, $cropWidth, $cropHeight);
            imageinterlace($canvas, false);

            ob_start();
            imagejpeg($canvas, null, 88);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($canvas);
            imagedestroy($source);
        }
    }

    /**
     * Recorte centrado que llena la proporción A4 sin deformar.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} x, y, ancho y alto del recorte
     */
    public static function coverCrop(int $width, int $height): array
    {
        $targetRatio = self::WIDTH / self::HEIGHT;
        $sourceRatio = $width / $height;

        if ($sourceRatio > $targetRatio) {
            $cropWidth = (int) round($height * $targetRatio);

            return [(int) floor(($width - $cropWidth) / 2), 0, $cropWidth, $height];
        }

        $cropHeight = (int) round($width / $targetRatio);

        return [0, (int) floor(($height - $cropHeight) / 2), $width, $cropHeight];
    }
}
