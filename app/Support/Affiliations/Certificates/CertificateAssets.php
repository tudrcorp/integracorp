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
