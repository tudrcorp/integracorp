<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Notificación WhatsApp
    |--------------------------------------------------------------------------
    */

    'notification_phone' => env('SCHEDULED_EXPORT_NOTIFICATION_PHONE', env('DB_BACKUP_NOTIFICATION_PHONE', '04127018390')),

    /*
    | Teléfonos: ver config/scheduled-notifications.php (SCHEDULED_NOTIFICATION_PHONES).
    */

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento
    |--------------------------------------------------------------------------
    */

    'directory' => 'scheduled-exports',

    'retention_days' => (int) env('SCHEDULED_EXPORT_RETENTION_DAYS', 7),

    'max_whatsapp_attachment_mb' => (int) env('SCHEDULED_EXPORT_MAX_WHATSAPP_MB', 50),

    'exports' => [
        'individual_affiliations' => [
            'directory' => 'individual-affiliations',
            'filename_prefix' => 'integracorp_afiliaciones_individuales',
        ],
        'corporate_affiliations' => [
            'directory' => 'corporate-affiliations',
            'filename_prefix' => 'integracorp_afiliaciones_corporativas',
        ],
        'agents' => [
            'directory' => 'agents',
            'filename_prefix' => 'integracorp_agentes',
            'service' => \App\Support\Exports\AgentsExportService::class,
            'title' => 'Exportación agentes',
            'description' => 'Genera un Excel .xlsx con todos los agentes, sus notas de bitácora y observaciones de estructura comercial, y lo envía por WhatsApp.',
            'record_metric_label' => 'Agentes exportados',
            'note_metric_label' => 'Notas incluidas',
            'execution_details' => [
                'Alcance' => 'Todos los agentes con notas de bitácora y observaciones comerciales',
                'Formato' => 'Excel .xlsx (1 fila por agente; notas concatenadas en columnas)',
            ],
            'reading_notes' => [
                'Las notas de bitácora y las observaciones de estructura comercial van en columnas separadas.',
                'Formato de nota: [fecha | autor] texto, separadas por ||.',
                'Si la ejecución es exitosa, recibirás imagen Integracorp + resumen + archivo .xlsx adjunto (en producción).',
            ],
        ],
        'agencies' => [
            'directory' => 'agencies',
            'filename_prefix' => 'integracorp_agencias',
            'service' => \App\Support\Exports\AgenciesExportService::class,
            'title' => 'Exportación agencias',
            'description' => 'Genera un Excel .xlsx con todas las agencias, sus notas de bitácora y observaciones de estructura comercial, y lo envía por WhatsApp.',
            'record_metric_label' => 'Agencias exportadas',
            'note_metric_label' => 'Notas incluidas',
            'execution_details' => [
                'Alcance' => 'Todas las agencias con notas de bitácora y observaciones comerciales',
                'Formato' => 'Excel .xlsx (1 fila por agencia; notas concatenadas en columnas)',
            ],
            'reading_notes' => [
                'Incluye comentarios inline, bitácora y observaciones de estructura comercial.',
                'Formato de nota: [fecha | autor] texto, separadas por ||.',
            ],
        ],
        'natural_providers' => [
            'directory' => 'natural-providers',
            'filename_prefix' => 'integracorp_proveedores_naturales',
            'service' => \App\Support\Exports\NaturalProvidersExportService::class,
            'title' => 'Exportación proveedores naturales',
            'description' => 'Genera un Excel .xlsx con proveedores naturales (doctor_nurses) y sus notas internas, y lo envía por WhatsApp.',
            'record_metric_label' => 'Proveedores naturales exportados',
            'note_metric_label' => 'Notas incluidas',
            'execution_details' => [
                'Alcance' => 'Todos los proveedores naturales con observaciones internas',
                'Formato' => 'Excel .xlsx (1 fila por proveedor; notas concatenadas)',
            ],
            'reading_notes' => [
                'Las notas provienen de doctor_nurse_observacions.',
                'Formato de nota: [fecha | autor] texto, separadas por ||.',
            ],
        ],
        'juridical_providers' => [
            'directory' => 'juridical-providers',
            'filename_prefix' => 'integracorp_proveedores_juridicos',
            'service' => \App\Support\Exports\JuridicalProvidersExportService::class,
            'title' => 'Exportación proveedores jurídicos',
            'description' => 'Genera un Excel .xlsx con proveedores jurídicos (suppliers), observaciones inline y bitácora, y lo envía por WhatsApp.',
            'record_metric_label' => 'Proveedores jurídicos exportados',
            'note_metric_label' => 'Notas incluidas',
            'execution_details' => [
                'Alcance' => 'Todos los proveedores jurídicos con observaciones y bitácora',
                'Formato' => 'Excel .xlsx (1 fila por proveedor; notas concatenadas)',
            ],
            'reading_notes' => [
                'Incluye campo observaciones inline y bitácora supplier_observacions.',
                'Formato de nota: [fecha | autor] texto, separadas por ||.',
            ],
        ],
        'collaborators' => [
            'directory' => 'collaborators',
            'filename_prefix' => 'integracorp_colaboradores',
            'service' => \App\Support\Exports\CollaboratorsExportService::class,
            'title' => 'Exportación colaboradores',
            'description' => 'Genera un Excel .xlsx con todos los colaboradores y lo envía por WhatsApp.',
            'record_metric_label' => 'Colaboradores exportados',
            'execution_details' => [
                'Alcance' => 'Todos los colaboradores registrados',
                'Formato' => 'Excel .xlsx (1 fila por colaborador)',
            ],
            'reading_notes' => [
                'Exportación de datos maestros de colaboradores (tabla collaborators).',
            ],
        ],
        'doctors' => [
            'directory' => 'doctors',
            'filename_prefix' => 'integracorp_doctores',
            'service' => \App\Support\Exports\TelemedicineDoctorsExportService::class,
            'title' => 'Exportación doctores',
            'description' => 'Genera un Excel .xlsx con doctores de telemedicina y su proveedor jurídico vinculado, y lo envía por WhatsApp.',
            'record_metric_label' => 'Doctores exportados',
            'execution_details' => [
                'Alcance' => 'Todos los doctores de telemedicina (telemedicine_doctors)',
                'Formato' => 'Excel .xlsx (1 fila por doctor)',
            ],
            'reading_notes' => [
                'Incluye datos del proveedor jurídico asociado cuando existe supplier_id.',
            ],
        ],
    ],

];
