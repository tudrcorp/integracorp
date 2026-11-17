<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Support\QrCode\GdPngQrCodeGenerator;

/**
 * Imágenes del certificado como RUTAS de archivo, nunca como data URI.
 *
 * Con data URI cada carnet incrustaba otra copia del logo y del QR: el HTML de
 * TDEC-COR-00054 (2.646 carnets) pesaba 133,5 MB con 7.943 imágenes, y DomPDF
 * las decodificaba y retenía una por una. Con rutas, DomPDF lee cada archivo una
 * sola vez (lo cachea por ruta) y el HTML queda en unos pocos MB.
 *
 * Las imágenes generadas (QR de la emisión, ondas del tema) se escriben una vez
 * en disco con un nombre derivado de su contenido: los tramos de un documento
 * largo, que se dibujan en jobs distintos, reutilizan los mismos archivos.
 * Todo vive dentro del `chroot` de DomPDF (la raíz del proyecto).
 */
final class CertificateAssets
{
    private const STATIC_DIRECTORY = 'image/certificado-afiliacion';

    public static function staticImage(string $file): string
    {
        $path = public_path(self::STATIC_DIRECTORY.'/'.$file);

        return is_file($path) ? $path : '';
    }

    public static function qr(string $verificationUrl): string
    {
        return self::generated('png', fn (): string => GdPngQrCodeGenerator::generate($verificationUrl, 264, 'M', 0), 'qr-'.$verificationUrl);
    }

    public static function wave(string $color, int $width, int $height, string $path): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" preserveAspectRatio="none" width="'.$width.'" height="'.$height.'">'
            .'<path d="'.$path.'" fill="'.$color.'"/></svg>';

        return self::generated('svg', static fn (): string => $svg, $svg);
    }

    /**
     * Copia de un logo PNG/JPG acomodada en un lienzo transparente de la caja
     * dada: alineada a la izquierda, centrada en vertical y sin deformar. '' si
     * el archivo no es una imagen válida.
     */
    public static function fittedImage(string $source, int $width, int $height): string
    {
        if (! is_file($source)) {
            return '';
        }

        $identity = 'fitted|'.$source.'|'.(int) @filemtime($source).'|'.(int) @filesize($source).'|'.$width.'x'.$height;
        $path = self::directory().'/'.sha1($identity).'.png';

        if (is_file($path)) {
            return $path;
        }

        $png = self::fit($source, $width, $height);

        return $png === null ? '' : self::generated('png', static fn (): string => $png, $identity);
    }

    private static function fit(string $source, int $width, int $height): ?string
    {
        $info = @getimagesize($source);

        if ($info === false || ! in_array($info[2] ?? null, [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            return null;
        }

        [$sourceWidth, $sourceHeight] = [(int) $info[0], (int) $info[1]];

        if ($sourceWidth < 1 || $sourceHeight < 1 || $sourceWidth * $sourceHeight > 40_000_000) {
            return null;
        }

        $image = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($source) : @imagecreatefromjpeg($source);

        if ($image === false) {
            return null;
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
        imagealphablending($canvas, true);

        $scale = min($width / $sourceWidth, $height / $sourceHeight);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        imagecopyresampled($canvas, $image, 0, (int) floor(($height - $targetHeight) / 2), 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        imagedestroy($image);

        ob_start();
        imagepng($canvas, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        return $png !== '' ? $png : null;
    }

    public static function directory(): string
    {
        return storage_path('app/certificados-generador/recursos');
    }

    /**
     * Escribe el archivo solo si no existe. La escritura va a un temporal y se
     * renombra: dos jobs que dibujan tramos a la vez nunca leen un archivo a medias.
     *
     * @param  callable(): string  $contents
     */
    private static function generated(string $extension, callable $contents, string $identity): string
    {
        $directory = self::directory();
        $path = $directory.'/'.sha1($identity).'.'.$extension;

        if (is_file($path)) {
            return $path;
        }

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temporary, $contents());
        @rename($temporary, $path);

        return is_file($path) ? $path : '';
    }
}
