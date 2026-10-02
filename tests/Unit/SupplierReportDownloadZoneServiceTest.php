<?php

declare(strict_types=1);

use App\Services\SupplierReportDownloadZoneService;
use App\Services\SupplierReportPdfService;

it('usa ruta relativa alineada con Filament download-zone y nombre del PDF', function (): void {
    expect(SupplierReportDownloadZoneService::relativeDocumentPath())
        ->toBe('download-zone/'.basename(SupplierReportPdfService::FILENAME));
});

it('resuelve la zona de descarga por configuracion documento o descripcion', function (): void {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/SupplierReportDownloadZoneService.php');
    $config = file_get_contents(dirname(__DIR__, 2).'/config/supplier-report.php');
    $listPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/ListSuppliers.php');

    expect($service)->not->toBeFalse()
        ->toContain('resolveDownloadZoneRecord')
        ->toContain('LEGACY_DOWNLOAD_ZONE_ID')
        ->toContain('notFoundUserMessage')
        ->toContain('SUPPLIER_REPORT_DOWNLOAD_ZONE_ID')
        ->and($config)
        ->toContain('download_zone_id')
        ->toContain('download_zone_description_needle')
        ->and($listPage)
        ->toContain('notFoundUserMessage()');
});
