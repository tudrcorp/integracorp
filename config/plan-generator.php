<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Memoria al generar el PDF del generador de planes (DomPDF)
    |--------------------------------------------------------------------------
    */

    'pdf_memory_limit' => env('PLAN_GENERATOR_PDF_MEMORY_LIMIT', env('SUPPLIER_REPORT_PDF_MEMORY_LIMIT', '512M')),

    /*
    |--------------------------------------------------------------------------
    | TTL de caché del binario PDF (segundos)
    |--------------------------------------------------------------------------
    |
    | La vista previa y la descarga reutilizan el PDF generado hasta que expire
    | el TTL o cambien los datos del plan (versión por updated_at e imágenes).
    |
    */

    'pdf_cache_ttl_seconds' => (int) env('PLAN_GENERATOR_PDF_CACHE_TTL', env('SUPPLIER_REPORT_PDF_CACHE_TTL', '900')),

    /*
    |--------------------------------------------------------------------------
    | Ancho máximo de imágenes embebidas en el PDF (px)
    |--------------------------------------------------------------------------
    */

    'pdf_image_max_width' => (int) env('PLAN_GENERATOR_PDF_IMAGE_MAX_WIDTH', '1240'),

];
