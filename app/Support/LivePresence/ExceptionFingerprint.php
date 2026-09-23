<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use Throwable;

/**
 * Lee una excepción (el objeto, o el texto que Laravel guarda en failed_jobs)
 * y la reduce a lo que sirve para corregirla: clase, mensaje, la línea de
 * NUESTRO código donde se originó y una huella estable para agrupar.
 *
 * La huella ignora lo que cambia entre repeticiones del mismo error (IDs,
 * UUIDs, correos, IPs, hashes) y conserva lo que lo distingue (códigos HTTP,
 * nombres de columna, el archivo y la línea).
 */
final class ExceptionFingerprint
{
    private const MAX_MESSAGE = 1200;

    private const MAX_FRAMES = 40;

    /** Carpetas del proyecto: un frame aquí es «nuestro código». */
    private const APP_FOLDERS = ['app/', 'routes/', 'resources/', 'database/', 'config/', 'bootstrap/app.php', 'storage/framework/views/'];

    /** Carpetas para recortar la ruta absoluta del servidor, sea cual sea. */
    private const PROJECT_FOLDERS = ['app', 'vendor', 'routes', 'resources', 'database', 'config', 'bootstrap', 'storage', 'public', 'tests'];

    /**
     * @return array{class: string, short_class: string, message: string, file: string, line: int|null, location: string, frames: list<array{file: string, line: int|null, call: string, app: bool}>, app_frames: list<array{file: string, line: int|null, call: string, app: bool}>, origin: string}
     */
    public static function fromThrowable(Throwable $exception): array
    {
        $frames = [];

        foreach (array_slice($exception->getTrace(), 0, self::MAX_FRAMES) as $frame) {
            $file = isset($frame['file']) ? self::relativePath((string) $frame['file']) : '[interno]';
            $call = trim(((string) ($frame['class'] ?? '')).((string) ($frame['type'] ?? '')).((string) ($frame['function'] ?? '')).'()');
            $frames[] = ['file' => $file, 'line' => isset($frame['line']) ? (int) $frame['line'] : null, 'call' => $call, 'app' => self::isAppFile($file)];
        }

        return self::assemble(
            $exception::class,
            $exception->getMessage(),
            self::relativePath($exception->getFile()),
            $exception->getLine(),
            $frames,
        );
    }

    /**
     * Texto de `failed_jobs.exception`: «Clase: mensaje in /ruta:línea\nStack trace:\n#0 …».
     *
     * @return array{class: string, short_class: string, message: string, file: string, line: int|null, location: string, frames: list<array{file: string, line: int|null, call: string, app: bool}>, app_frames: list<array{file: string, line: int|null, call: string, app: bool}>, origin: string}
     */
    public static function fromReport(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);
        $parts = explode("\nStack trace:", $text, 2);
        $head = trim($parts[0]);
        $class = 'Exception';
        $message = $head;
        $file = '';
        $line = null;

        if (preg_match('/^(?<class>[A-Za-z_\\\\][A-Za-z0-9_\\\\]*): (?<message>.*?)(?: in (?<file>\S+?):(?<line>\d+))?$/s', $head, $match) === 1) {
            $class = $match['class'];
            $message = $match['message'];
            $file = isset($match['file']) && $match['file'] !== '' ? self::relativePath($match['file']) : '';
            $line = isset($match['line']) && $match['line'] !== '' ? (int) $match['line'] : null;
        }

        $frames = [];

        if (isset($parts[1])) {
            foreach (explode("\n", $parts[1]) as $row) {
                if (count($frames) >= self::MAX_FRAMES) {
                    break;
                }

                if (preg_match('/^#\d+ (?<file>[^()]+?)\((?<line>\d+)\): (?<call>.*)$/', trim($row), $frame) === 1) {
                    $path = self::relativePath($frame['file']);
                    $frames[] = ['file' => $path, 'line' => (int) $frame['line'], 'call' => self::shortCall($frame['call']), 'app' => self::isAppFile($path)];
                } elseif (preg_match('/^#\d+ \[internal function\]: (?<call>.*)$/', trim($row), $frame) === 1) {
                    $frames[] = ['file' => '[interno]', 'line' => null, 'call' => self::shortCall($frame['call']), 'app' => false];
                }
            }
        }

        return self::assemble($class, $message, $file, $line, $frames);
    }

    /**
     * Huella estable de un error dentro de un contexto (el trabajo, la ruta…).
     */
    public static function fingerprint(string $context, string $class, string $message, string $origin): string
    {
        return substr(sha1($context.'|'.$class.'|'.self::normalizeMessage($message).'|'.$origin), 0, 16);
    }

    /**
     * Quita del mensaje lo que cambia entre repeticiones del mismo error.
     */
    public static function normalizeMessage(string $message): string
    {
        $message = mb_substr($message, 0, 400);

        $patterns = [
            '#https?://\S+#i' => '{url}',
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i' => '{uuid}',
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i' => '{correo}',
            '/\b\d{1,3}(?:\.\d{1,3}){3}\b/' => '{ip}',
            '/\b[0-9a-f]{16,}\b/i' => '{hash}',
            /** Identificadores mixtos: transacciones SMTP, nombres de archivo con correlativo (CER-TDEC-IND-000244.pdf). */
            '/(?<![\w.-])(?=[\w.-]*\d)(?=[\w.-]*[A-Za-z])[\w.-]{12,}(?![\w.-])/' => '{id}',
            '/\b\d{5,}\b/' => '{n}',
            '/(\bid\b["\']?\s*[=:]\s*)\d+/i' => '$1{n}',
            '/#\d+/' => '#{n}',
            '/\(Connection: [^,]+, SQL: .*\)$/s' => '',
            '/\s+/' => ' ',
        ];

        return trim((string) preg_replace(array_keys($patterns), array_values($patterns), $message));
    }

    /**
     * Ruta relativa al proyecto, venga del servidor que venga
     * (/var/www/…/integracorp/app/Jobs/X.php → app/Jobs/X.php).
     */
    public static function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';

        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base));
        }

        /**
         * La primera carpeta del proyecto que aparece en la ruta; si la siguiente
         * también lo es (/srv/app/vendor/…, /srv/app/app/…), la raíz era la
         * primera y se avanza a la segunda.
         */
        $pattern = '#/('.implode('|', self::PROJECT_FOLDERS).')/#';

        if (preg_match($pattern, $path, $match, PREG_OFFSET_CAPTURE) === 1) {
            $start = $match[1][1];

            while (preg_match('#^('.implode('|', self::PROJECT_FOLDERS).')/('.implode('|', self::PROJECT_FOLDERS).')/#', substr($path, $start), $nested) === 1) {
                $start += strlen($nested[1]) + 1;
            }

            return substr($path, $start);
        }

        return $path;
    }

    public static function isAppFile(string $relativePath): bool
    {
        foreach (self::APP_FOLDERS as $folder) {
            if (str_starts_with($relativePath, $folder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{file: string, line: int|null, call: string, app: bool}>  $frames
     * @return array{class: string, short_class: string, message: string, file: string, line: int|null, location: string, frames: list<array{file: string, line: int|null, call: string, app: bool}>, app_frames: list<array{file: string, line: int|null, call: string, app: bool}>, origin: string}
     */
    private static function assemble(string $class, string $message, string $file, ?int $line, array $frames): array
    {
        $appFrames = array_values(array_filter($frames, static fn (array $frame): bool => $frame['app']));
        $location = $file !== '' ? $file.($line !== null ? ':'.$line : '') : '';

        /** Dónde corregir: si el error se lanzó dentro de vendor, la primera línea nuestra que lo llamó. */
        $origin = self::isAppFile($file) || $appFrames === []
            ? $location
            : $appFrames[0]['file'].($appFrames[0]['line'] !== null ? ':'.$appFrames[0]['line'] : '');

        return [
            'class' => $class,
            'short_class' => class_basename($class),
            'message' => mb_strimwidth(trim($message), 0, self::MAX_MESSAGE, '…'),
            'file' => $file,
            'line' => $line,
            'location' => $location,
            'frames' => $frames,
            'app_frames' => $appFrames,
            'origin' => $origin,
        ];
    }

    private static function shortCall(string $call): string
    {
        return mb_strimwidth((string) preg_replace('/\((.{60}).*\)$/s', '($1…)', $call), 0, 160, '…');
    }
}
