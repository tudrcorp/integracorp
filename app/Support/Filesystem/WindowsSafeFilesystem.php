<?php

declare(strict_types=1);

namespace App\Support\Filesystem;

use ErrorException;
use Illuminate\Filesystem\Filesystem;

/**
 * En Windows, `rename()` sobre una vista Blade ya abierta (opcache, antivirus,
 * watcher del IDE o otro worker de Apache) falla con «Acceso denegado (code: 5)».
 * Reintenta el reemplazo atómico y, si hace falta, cae a copy+unlink.
 */
final class WindowsSafeFilesystem extends Filesystem
{
    public function replace($path, $content, $mode = null): void
    {
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;
        $tempPath = tempnam(dirname($path), basename($path));

        if ($tempPath === false) {
            throw new ErrorException("No se pudo crear el archivo temporal para {$path}.");
        }

        if (! is_null($mode)) {
            chmod($tempPath, $mode);
        } else {
            chmod($tempPath, 0777 - umask());
        }

        file_put_contents($tempPath, $content);

        $attempts = 8;
        $lastMessage = null;

        while ($attempts-- > 0) {
            try {
                if (is_file($path)) {
                    @unlink($path);
                }

                if (@rename($tempPath, $path)) {
                    return;
                }

                if (is_file($path) && is_file($tempPath) && md5_file($path) === md5_file($tempPath)) {
                    @unlink($tempPath);

                    return;
                }

                if (@copy($tempPath, $path)) {
                    @unlink($tempPath);

                    return;
                }
            } catch (\Throwable $exception) {
                $lastMessage = $exception->getMessage();
            }

            usleep(75_000);
            clearstatcache(true, $path);
            clearstatcache(true, $tempPath);
        }

        @unlink($tempPath);

        throw new ErrorException(
            $lastMessage
                ?? "rename({$tempPath},{$path}): Acceso denegado (code: 5)"
        );
    }
}
