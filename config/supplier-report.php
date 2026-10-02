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
    | TTL de caché del binario PDF (segundos)
    |--------------------------------------------------------------------------
    |
    | Tras la primera generación, vista previa / descarga / correo reutilizan el PDF hasta que
    | expire el TTL o cambien los datos (versión por conteo + updated_at de suppliers).
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
