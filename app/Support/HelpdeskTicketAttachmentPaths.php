<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normaliza el valor de `help_desks.image` (Filament FileUpload: una ruta o JSON con varias).
 *
 * @phpstan-return list<string>
 */
final class HelpdeskTicketAttachmentPaths
{
    /**
     * @return list<string> Rutas relativas al disco `public`.
     */
    public static function fromDatabaseValue(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        if (is_array($raw)) {
            return self::filterPaths(array_map(static fn (mixed $v): string => is_string($v) ? trim($v) : '', $raw));
        }

        if (! is_string($raw)) {
            return [];
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        if (str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return self::filterPaths(array_map(static fn (mixed $v): string => is_string($v) ? trim($v) : '', $decoded));
            }
        }

        return self::filterPaths([$trimmed]);
    }

    /**
     * @param  array<int, string>  $paths
     * @return list<string>
     */
    private static function filterPaths(array $paths): array
    {
        $out = [];
        foreach ($paths as $p) {
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }
}
