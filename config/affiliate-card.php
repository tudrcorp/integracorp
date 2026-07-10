<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Generación de carnets por estampado (FPDI)
    |--------------------------------------------------------------------------
    |
    | Cuando está activo y existe la plantilla del plan, se evita DomPDF y solo
    | se estampan los campos variables sobre un PDF base pregenerado.
    |
    */
    'stamped_generation_enabled' => env('AFFILIATE_CARD_STAMPED_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Cola de jobs de documentos de asociados
    |--------------------------------------------------------------------------
    */
    'documents_queue' => env('AFFILIATE_CARD_DOCUMENTS_QUEUE', 'documents'),

    'templates_path' => resource_path('affiliate-card/templates'),

    'qr_plans_path' => public_path('storage/tarjeta-afiliacion/planes'),

    'template_keys' => [
        'qr-plan-inclusion.png' => 'inclusion',
        'qr-plan-inicial.png' => 'inicial',
        'qr-plan-ideal.png' => 'ideal',
        'qr-plan-especial.png' => 'especial',
    ],

    /*
    |--------------------------------------------------------------------------
    | Plantillas base sin mapeo por QR (p. ej. carnet FEDEVIP v2 individual)
    |--------------------------------------------------------------------------
    */
    'standalone_template_keys' => [
        'individual',
        'individual-affiliation',
    ],

    'individual_affiliation_template_image' => public_path('storage/certificados/tarjeta-afiliado-individual-cropped.png'),

];
