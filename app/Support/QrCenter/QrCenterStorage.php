<?php

declare(strict_types=1);

namespace App\Support\QrCenter;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Archivos del centro de QR en el disco privado `local`: imágenes de cada versión
 * de un destino y el HTML de cada versión de una página alojada.
 *
 * El disco tiene `throw => false`: una escritura que falla no lanza excepción por
 * sí sola, así que toda escritura se verifica aquí.
 */
final class QrCenterStorage
{
    public const DISK = 'local';

    public static function destinationImagePath(int $destinationId, int $version): string
    {
        return 'qr-center/destinos/'.$destinationId.'/v'.$version.'.png';
    }

    public static function hostedPagePath(int $pageId, int $version): string
    {
        return 'qr-center/paginas/'.$pageId.'/v'.$version.'.html';
    }

    public static function put(string $relative, string $contents): void
    {
        $disk = Storage::disk(self::DISK);
        $directory = dirname($relative);

        if (! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        if ($disk->put($relative, $contents) !== true) {
            throw new RuntimeException('No se pudo guardar '.$relative.'. Revise los permisos de storage/app/private.');
        }
    }

    public static function copy(string $from, string $to): void
    {
        $contents = self::get($from);

        if ($contents === null) {
            throw new RuntimeException('No se encontró el archivo '.$from.' para copiarlo.');
        }

        self::put($to, $contents);
    }

    public static function get(string $relative): ?string
    {
        $contents = Storage::disk(self::DISK)->get($relative);

        return is_string($contents) ? $contents : null;
    }

    public static function exists(?string $relative): bool
    {
        return is_string($relative) && $relative !== '' && Storage::disk(self::DISK)->exists($relative);
    }

    public static function absolutePath(string $relative): string
    {
        return Storage::disk(self::DISK)->path($relative);
    }

    public static function delete(string $relative): void
    {
        Storage::disk(self::DISK)->delete($relative);
    }
}
