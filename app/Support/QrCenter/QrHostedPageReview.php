<?php

declare(strict_types=1);

namespace App\Support\QrCenter;

/**
 * Revisión de un HTML antes de publicarlo como página alojada.
 *
 * Los errores bloquean la publicación; los avisos se muestran y se guardan con la
 * versión. La seguridad no depende de esta revisión: la página se sirve aislada
 * (CSP `sandbox`), así que su JavaScript no alcanza la sesión de IntegraCorp.
 */
final class QrHostedPageReview
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const LEVEL_OK = 'ok';

    public const LEVEL_WARN = 'warn';

    public const LEVEL_ERROR = 'error';

    /** Servidores de recursos que, si no responden, dejan la página sin íconos, fuentes o estilos. */
    private const CDN_HOSTS = [
        'unpkg.com',
        'cdn.jsdelivr.net',
        'cdnjs.cloudflare.com',
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'cdn.tailwindcss.com',
        'code.jquery.com',
    ];

    /**
     * @return list<array{level: string, title: string, detail: string}>
     */
    public static function review(string $html): array
    {
        $bytes = strlen($html);
        $checks = [];

        $isHtml = (bool) preg_match('/<!doctype\s+html|<html[\s>]/i', substr($html, 0, 4096));
        $checks[] = $isHtml
            ? self::check(self::LEVEL_OK, 'Archivo HTML válido', 'Empieza como un documento HTML.')
            : self::check(self::LEVEL_ERROR, 'No parece un archivo HTML', 'Debe empezar con <!DOCTYPE html> o <html>.');

        $checks[] = $bytes <= self::MAX_BYTES
            ? self::check(self::LEVEL_OK, 'Tamaño permitido', self::humanSize($bytes).' de '.self::humanSize(self::MAX_BYTES).'.')
            : self::check(self::LEVEL_ERROR, 'El archivo pesa demasiado', self::humanSize($bytes).'; el máximo es '.self::humanSize(self::MAX_BYTES).'.');

        if (! mb_check_encoding($html, 'UTF-8')) {
            $checks[] = self::check(self::LEVEL_WARN, 'La codificación no es UTF-8', 'Las tildes y la ñ pueden verse mal.');
        }

        // En las páginas empaquetadas la plantilla va dentro de un JSON: las comillas llegan escapadas (\").
        $checks[] = preg_match('/<meta[^>]+name\s*=\s*\\\\?["\']?viewport/i', $html)
            ? self::check(self::LEVEL_OK, 'Se adapta al móvil', 'Declara el ancho de pantalla.')
            : self::check(self::LEVEL_WARN, 'Puede verse pequeña en el móvil', 'No declara <meta name="viewport">.');

        $hosts = self::externalResourceHosts($html);
        $checks[] = $hosts === []
            ? self::check(self::LEVEL_OK, 'No depende de otros servidores', 'Imágenes, estilos y código vienen dentro del archivo.')
            : self::check(self::LEVEL_WARN, 'Carga recursos de internet', 'Usa '.implode(', ', $hosts).'. Si no responden, la página puede verse incompleta.');

        return $checks;
    }

    /**
     * @param  list<array{level: string, title: string, detail: string}>  $checks
     */
    public static function blocks(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['level'] === self::LEVEL_ERROR) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{level: string, title: string, detail: string}>  $checks
     */
    public static function firstError(array $checks): ?string
    {
        foreach ($checks as $check) {
            if ($check['level'] === self::LEVEL_ERROR) {
                return $check['title'].': '.$check['detail'];
            }
        }

        return null;
    }

    /**
     * Servidores de los que la página descarga recursos (no los enlaces que el
     * visitante toca, como WhatsApp o un correo).
     *
     * @return list<string>
     */
    public static function externalResourceHosts(string $html): array
    {
        $texts = [$html, ...self::bundledTextAssets($html)];
        $hosts = [];

        foreach ($texts as $text) {
            preg_match_all('/(?:\bsrc\s*=\s*["\']?|<link\b[^>]*\bhref\s*=\s*["\']?|url\(\s*["\']?|@import\s+(?:url\()?\s*["\']?)https?:\/\/([a-z0-9.-]+)/i', $text, $resources);
            $hosts = [...$hosts, ...$resources[1]];

            preg_match_all('/https?:\/\/([a-z0-9.-]+)/i', $text, $any);

            foreach ($any[1] as $host) {
                if (in_array(strtolower($host), self::CDN_HOSTS, true)) {
                    $hosts[] = $host;
                }
            }
        }

        $hosts = array_values(array_unique(array_map('strtolower', $hosts)));
        sort($hosts);

        return $hosts;
    }

    /**
     * Las páginas empaquetadas («bundler») guardan su código comprimido en un
     * manifiesto JSON: se descomprime para que la revisión vea lo que realmente carga.
     *
     * @return list<string>
     */
    private static function bundledTextAssets(string $html): array
    {
        if (! preg_match('/<script type="__bundler\/manifest"[^>]*>(.*?)<\/script>/s', $html, $match)) {
            return [];
        }

        $manifest = json_decode($match[1], true);

        if (! is_array($manifest)) {
            return [];
        }

        $texts = [];

        foreach ($manifest as $asset) {
            if (! is_array($asset) || ! is_string($asset['data'] ?? null) || ! str_contains((string) ($asset['mime'] ?? ''), 'script')) {
                continue;
            }

            $binary = base64_decode($asset['data'], true);

            if ($binary === false) {
                continue;
            }

            if (($asset['compressed'] ?? false) === true) {
                $binary = @gzdecode($binary);
            }

            if (is_string($binary)) {
                $texts[] = $binary;
            }
        }

        return $texts;
    }

    /**
     * @return array{level: string, title: string, detail: string}
     */
    private static function check(string $level, string $title, string $detail): array
    {
        return ['level' => $level, 'title' => $title, 'detail' => $detail];
    }

    public static function humanSize(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? number_format($bytes / 1024 / 1024, 1, ',', '.').' MB'
            : number_format(max(1, (int) round($bytes / 1024)), 0, ',', '.').' KB';
    }
}
