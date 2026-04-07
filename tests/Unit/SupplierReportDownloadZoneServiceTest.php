<?php

declare(strict_types=1);

use App\Services\SupplierReportDownloadZoneService;
use App\Services\SupplierReportPdfService;

it('usa ruta relativa alineada con Filament download-zone y nombre del PDF', function (): void {
    expect(SupplierReportDownloadZoneService::relativeDocumentPath())
        ->toBe('download-zone/'.basename(SupplierReportPdfService::FILENAME));
});

it('usa id fijo 21 para el registro de zona de descarga', function (): void {
    expect(SupplierReportDownloadZoneService::DOWNLOAD_ZONE_ID)->toBe(21);
});
