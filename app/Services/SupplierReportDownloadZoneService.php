<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DownloadZone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

class SupplierReportDownloadZoneService
{
    /**
     * Registro en {@see DownloadZone} que recibe el PDF del listado de proveedores.
     */
    public const DOWNLOAD_ZONE_ID = 21;

    /**
     * Ruta relativa al disco {@code public} (misma convención que Filament: {@code download-zone/...}).
     */
    public static function relativeDocumentPath(): string
    {
        return 'download-zone/'.basename(SupplierReportPdfService::FILENAME);
    }

    /**
     * Genera el PDF, lo escribe en {@code storage/app/public/download-zone/} y actualiza el campo {@code document} del registro.
     *
     * @throws ModelNotFoundException Si no existe {@see self::DOWNLOAD_ZONE_ID}
     */
    public static function publish(): DownloadZone
    {
        $record = DownloadZone::query()->find(self::DOWNLOAD_ZONE_ID);

        if ($record === null) {
            throw (new ModelNotFoundException)->setModel(DownloadZone::class, [self::DOWNLOAD_ZONE_ID]);
        }

        $relativePath = self::relativeDocumentPath();
        $disk = Storage::disk('public');

        $disk->makeDirectory('download-zone');

        $binary = SupplierReportPdfService::make()->output();

        $previousDocument = $record->document;

        $disk->put($relativePath, $binary);

        if (is_string($previousDocument)
            && $previousDocument !== ''
            && $previousDocument !== $relativePath
            && $disk->exists($previousDocument)) {
            $disk->delete($previousDocument);
        }

        $record->update([
            'document' => $relativePath,
        ]);

        return $record->fresh();
    }
}
