<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Memoria al generar el PDF de proveedores (DomPDF)
    |--------------------------------------------------------------------------
    |
    | Listados muy grandes pueden necesitar más memoria. Vacío = no modificar php.ini.
    |
    */

    'pdf_memory_limit' => env('SUPPLIER_REPORT_PDF_MEMORY_LIMIT', '512M'),

    /*
    |--------------------------------------------------------------------------
    | Retraso de la regeneración en cola (segundos)
    |--------------------------------------------------------------------------
    |
    | El PDF se guarda en disco (storage/app/private/supplier-report) y lo regenera
    | RegenerateSupplierReportPdfJob al cambiar un proveedor. El retraso agrupa
    | varias ediciones seguidas en una sola generación.
    |
    */

    'regenerate_delay_seconds' => (int) env('SUPPLIER_REPORT_REGENERATE_DELAY', '60'),

    /*
    |--------------------------------------------------------------------------
    | TTL de caché de PDF (segundos)
    |--------------------------------------------------------------------------
    |
    | Ya no lo usa el reporte de proveedores (vive en disco), pero sí las fichas
    | de afiliación y de médicos/enfermeros (AffiliationFichaPdfService,
    | DoctorNurseFichaPdfService). No borrar.
    |
    */

    'pdf_cache_ttl_seconds' => (int) env('SUPPLIER_REPORT_PDF_CACHE_TTL', '900'),

    /*
    |--------------------------------------------------------------------------
    | Registro en Zona de descargas (Operaciones)
    |--------------------------------------------------------------------------
    |
    | ID del documento en download_zones que recibe el PDF del listado de proveedores.
    | Si está vacío, se busca por documento reporte-proveedores.pdf o descripción
    | que contenga download_zone_description_needle (p. ej. «RED DE PROVEEDORES»).
    |
    */

    'download_zone_id' => filled(env('SUPPLIER_REPORT_DOWNLOAD_ZONE_ID'))
        ? (int) env('SUPPLIER_REPORT_DOWNLOAD_ZONE_ID')
        : null,

    'download_zone_description_needle' => env(
        'SUPPLIER_REPORT_DOWNLOAD_ZONE_DESCRIPTION',
        'RED DE PROVEEDORES',
    ),

];
