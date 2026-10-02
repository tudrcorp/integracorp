<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DownloadZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

class SupplierReportDownloadZoneService
{
    /**
     * ID histórico en producción; solo se usa si el registro sigue existiendo.
     *
     * @deprecated Preferir {@see config('supplier-report.download_zone_id')} o resolución por descripción/documento.
     */
    public const LEGACY_DOWNLOAD_ZONE_ID = 21;

    /**
     * Ruta relativa al disco {@code public} (misma convención que Filament: {@code download-zone/...}).
     */
    public static function relativeDocumentPath(): string
    {
        return 'download-zone/'.basename(SupplierReportPdfService::FILENAME);
    }

    /**
     * @throws ModelNotFoundException
     */
    public static function resolveDownloadZoneRecord(): DownloadZone
    {
        $configuredId = self::configuredDownloadZoneId();
        if ($configuredId !== null) {
            $record = DownloadZone::query()->find($configuredId);
            if ($record !== null) {
                return $record;
            }
        }

        if ($configuredId === null) {
            $legacy = DownloadZone::query()->find(self::LEGACY_DOWNLOAD_ZONE_ID);
            if ($legacy !== null) {
                return $legacy;
            }
        }

        $relativePath = self::relativeDocumentPath();
        $filename = basename(SupplierReportPdfService::FILENAME);

        $byDocument = DownloadZone::query()
            ->where(function (Builder $query) use ($relativePath, $filename): void {
                $query->where('document', $relativePath)
                    ->orWhere('document', 'like', '%'.$filename);
            })
            ->orderBy('id')
            ->first();

        if ($byDocument !== null) {
            return $byDocument;
        }

        $descriptionNeedle = (string) config('supplier-report.download_zone_description_needle', 'RED DE PROVEEDORES');
        $descriptionNeedle = trim($descriptionNeedle);

        if ($descriptionNeedle !== '') {
            $byDescription = DownloadZone::query()
                ->whereRaw('UPPER(description) LIKE ?', ['%'.mb_strtoupper($descriptionNeedle, 'UTF-8').'%'])
                ->orderBy('id')
                ->first();

            if ($byDescription !== null) {
                return $byDescription;
            }
        }

        $attemptedIds = array_values(array_filter([
            $configuredId,
            self::LEGACY_DOWNLOAD_ZONE_ID,
        ], static fn (?int $id): bool => $id !== null && $id > 0));

        throw (new ModelNotFoundException)->setModel(DownloadZone::class, $attemptedIds);
    }

    public static function notFoundUserMessage(): string
    {
        $configuredId = self::configuredDownloadZoneId();

        $hint = 'Crea el documento en Operaciones → Zona de descargas (descripción con «RED DE PROVEEDORES» o el PDF «'
            .basename(SupplierReportPdfService::FILENAME).'») o define SUPPLIER_REPORT_DOWNLOAD_ZONE_ID en .env con el ID del registro.';

        if ($configuredId !== null) {
            return 'No existe la zona de descarga con ID '.$configuredId.'. '.$hint;
        }

        return 'No se encontró un registro en Zona de descargas para publicar el reporte de proveedores. '.$hint;
    }

    /**
     * Genera el PDF, lo escribe en {@code storage/app/public/download-zone/} y actualiza el campo {@code document} del registro.
     *
     * @throws ModelNotFoundException Si no hay registro destino en {@see DownloadZone}
     */
    public static function publish(): DownloadZone
    {
        $record = self::resolveDownloadZoneRecord();

        $relativePath = self::relativeDocumentPath();
        $disk = Storage::disk('public');

        $disk->makeDirectory('download-zone');

        $binary = SupplierReportPdfService::outputBinaryCached();

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

    private static function configuredDownloadZoneId(): ?int
    {
        $value = config('supplier-report.download_zone_id');

        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
