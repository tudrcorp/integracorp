<?php

namespace App\Support;

use App\Models\HelpDesk;
use Illuminate\Support\Facades\Storage;

final class HelpdeskDocumentPaths
{
    /**
     * Rutas relativas al disco `public` guardadas en `help_desks.image` (una ruta o JSON de rutas).
     *
     * @return list<string>
     */
    public static function paths(HelpDesk $record): array
    {
        $raw = $record->image;
        if (blank($raw)) {
            return [];
        }

        if (! is_string($raw)) {
            return [];
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $paths = [];
            foreach ($decoded as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $paths[] = trim($item);
                }
            }

            return array_values($paths);
        }

        return [$trimmed];
    }

    /**
     * Metadatos para vista previa en el panel (disco public).
     *
     * @return list<array{path: string, url: string, extension: string, missing: bool, basename: string}>
     */
    public static function forPublicDisk(HelpDesk $record): array
    {
        $disk = Storage::disk('public');
        $out = [];

        foreach (self::paths($record) as $path) {
            $exists = $disk->exists($path);
            $out[] = [
                'path' => $path,
                'url' => $exists ? $disk->url($path) : '',
                'extension' => $exists ? strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) : '',
                'missing' => ! $exists,
                'basename' => basename($path),
            ];
        }

        return $out;
    }
}
