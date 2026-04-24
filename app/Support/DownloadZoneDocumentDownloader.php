<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DownloadZone;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadZoneDocumentDownloader
{
    /**
     * Registra traza de seguridad (LogController vía SecurityAudit), log de aplicación y entrega el archivo del disco public.
     *
     * @param  non-empty-string  $auditPanel  Identificador de panel: agents, master, general.
     */
    public static function download(DownloadZone $record, string $auditPanel): ?StreamedResponse
    {
        $relative = ltrim((string) $record->document, '/');
        if ($relative === '') {
            self::notifyMissing();

            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relative)) {
            SecurityAudit::log('AUDIT_DOWNLOAD_ZONE_DOCUMENT_NOT_FOUND', "{$auditPanel}.download-zones.download", [
                'download_zone_id' => $record->id,
                'zone_id' => $record->zone_id,
                'document_path' => $relative,
            ]);

            Log::warning('Zona de descarga: archivo solicitado no existe en disco.', [
                'panel' => $auditPanel,
                'download_zone_id' => $record->id,
                'zone_id' => $record->zone_id,
                'document_path' => $relative,
                'user_id' => auth()->id(),
            ]);

            self::notifyMissing();

            return null;
        }

        $absolutePath = $disk->path($relative);

        SecurityAudit::log('AUDIT_DOWNLOAD_ZONE_DOCUMENT_DOWNLOADED', "{$auditPanel}.download-zones.download", [
            'download_zone_id' => $record->id,
            'zone_id' => $record->zone_id,
            'description' => $record->description,
            'document_path' => $relative,
            'file_size_bytes' => @filesize($absolutePath) ?: null,
        ]);

        Log::info('Zona de descarga: documento descargado por el usuario.', [
            'panel' => $auditPanel,
            'download_zone_id' => $record->id,
            'zone_id' => $record->zone_id,
            'document_path' => $relative,
            'user_id' => auth()->id(),
        ]);

        return $disk->download($relative, basename($relative));
    }

    private static function notifyMissing(): void
    {
        Notification::make()
            ->title('Archivo no disponible')
            ->body('El documento no existe o fue retirado. Contacte a soporte si necesita el archivo.')
            ->warning()
            ->send();
    }
}
