<?php

declare(strict_types=1);

/**
 * Puerta de entrada del CRM de atención por WhatsApp.
 *
 * n8n entrega el handoff aquí. La petición solo verifica la firma y encola
 * en Redis: no abre sesión, no usa la cola `database` y no consulta afiliados.
 */
return [

    'secret' => env('CRM_HANDOFF_SECRET', ''),

    /** Segundos de margen del reloj entre n8n e IntegraCorp. */
    'tolerance' => (int) env('CRM_HANDOFF_TOLERANCE', 300),

    /**
     * Producción: `redis`. En local, sin Redis, `sync` guarda el sobre en la
     * misma petición. `database` se ignora para no usar la tabla `jobs`.
     */
    'queue_connection' => env('CRM_HANDOFF_QUEUE_CONNECTION', 'redis'),

    /**
     * Dónde se recuerda un handoff ya recibido. Producción: `crm-inbox` (Redis).
     * Local sin Redis: `file`.
     */
    'cache_store' => env('CRM_HANDOFF_CACHE_STORE', 'crm-inbox'),

    'queue' => env('CRM_HANDOFF_QUEUE', 'inbox'),

    /** Tope por IP y por minuto. n8n sale de una sola IP. */
    'rate_per_minute' => (int) env('CRM_HANDOFF_RATE_PER_MINUTE', 600),

    'max_bytes' => 65536,

    'idempotency_ttl_days' => 7,

    /** POST /webhook/handoff/takeover de n8n. Vacío: Tomar no silencia al bot. */
    'takeover_url' => env('CRM_N8N_TAKEOVER_URL', ''),

    /**
     * POST /webhook/handoff/reply de n8n. Vacío: se arma con la URL de Tomar,
     * cambiando takeover por reply.
     */
    'reply_url' => env('CRM_N8N_REPLY_URL', ''),

    /**
     * POST /webhook/handoff/document de n8n. Vacío: se arma con la URL de Tomar,
     * cambiando takeover por document.
     */
    'document_url' => env('CRM_N8N_DOCUMENT_URL', ''),

    /**
     * POST /webhook/handoff/release de n8n. Vacío: se arma con la URL de Tomar,
     * cambiando takeover por release.
     */
    'release_url' => env('CRM_N8N_RELEASE_URL', ''),

    /**
     * Finalizar conversación: el bot vuelve a responder y el próximo mensaje del cliente abre una
     * conversación nueva. Vacío: usa la URL de Tomar cambiando takeover por finish.
     */
    'finish_url' => env('CRM_N8N_FINISH_URL', ''),

    'takeover_key' => env('CRM_N8N_HANDOFF_KEY', ''),

    /**
     * Supervisión WhatsApp: métricas del bot (webhook de solo lectura en n8n). Vacío: usa la URL de
     * Tomar cambiando takeover por metrics.
     */
    'metrics_url' => env('CRM_N8N_METRICS_URL', ''),

    /** Salud de n8n. Vacío: /healthz en el mismo host y puerto de la URL de Tomar. */
    'n8n_health_url' => env('CRM_N8N_HEALTH_URL', ''),

    /**
     * Evolution API (número de WhatsApp del bot), en el mismo servidor. Solo se consulta el estado
     * de la conexión; la clave nunca sale de este servidor. Clave vacía: la salud de WhatsApp se
     * muestra como «sin configurar».
     */
    'evolution' => [
        'url' => env('CRM_EVOLUTION_URL', 'http://127.0.0.1:8081'),
        'key' => env('CRM_EVOLUTION_API_KEY', ''),
        'instance' => env('CRM_EVOLUTION_INSTANCE', 'sol'),
    ],

    /**
     * Archivos que el cliente envía por WhatsApp (POST /api/crm/handoff/media). n8n los descarga de
     * Evolution y los manda en base64, firmados como el resto. Se guardan en el disco privado y, al
     * cerrar la conversación, el analista los agrega al caso de telemedicina del paciente.
     * Videos: no se reciben (el bot avisa al cliente que no los procesa).
     */
    'media' => [
        'max_mb' => (int) env('CRM_MEDIA_MAX_MB', 16),

        /** Días que se guarda un archivo después de cerrada su conversación (o de recibido, si nunca tuvo caso). */
        'retention_days' => (int) env('CRM_MEDIA_RETENTION_DAYS', 180),

        'disk' => env('CRM_MEDIA_DISK', 'local'),

        'directory' => 'crm/attachments',

        /** Minutos que vale el enlace firmado de descarga que ve el analista. */
        'link_minutes' => 30,

        /** Tipo real del archivo (leído del contenido) => [tipo para la pantalla, extensión]. */
        'allowed' => [
            'application/pdf' => ['document', 'pdf'],
            'image/jpeg' => ['image', 'jpg'],
            'image/png' => ['image', 'png'],
            'image/webp' => ['image', 'webp'],
            'image/heic' => ['image', 'heic'],
            'image/heif' => ['image', 'heif'],
            'audio/ogg' => ['audio', 'ogg'],
            'audio/mpeg' => ['audio', 'mp3'],
            'audio/mp4' => ['audio', 'm4a'],
            'audio/aac' => ['audio', 'aac'],
            'audio/amr' => ['audio', 'amr'],
            'application/msword' => ['document', 'doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['document', 'docx'],
            'application/vnd.ms-excel' => ['document', 'xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['document', 'xlsx'],
        ],
    ],

    /** Minutos seguidos caído antes de avisar a los SUPERADMIN por la campanita y por correo. */
    'health_alert_minutes' => (int) env('CRM_HEALTH_ALERT_MINUTES', 2),

    /** Clave pública para que el navegador acepte avisos. Vacía: el botón no se ofrece. */
    'push_public_key' => env('CRM_PUSH_VAPID_PUBLIC_KEY', ''),

    /** Clave privada. La usa el aviso al caer un caso. No sale al navegador. */
    'push_private_key' => env('CRM_PUSH_VAPID_PRIVATE_KEY', ''),

    'push_subject' => env('CRM_PUSH_VAPID_SUBJECT', 'mailto:crm@tudrencasa.com'),

    /** Minutos de espera en que la bandeja pasa a ámbar y a rojo. */
    'sla' => [
        'warn_minutes' => (int) env('CRM_INBOX_SLA_WARN_MINUTES', 5),
        'late_minutes' => (int) env('CRM_INBOX_SLA_LATE_MINUTES', 15),
        /** Minutos sin respuesta del analista que lo atiende: se avisa al supervisor y un compañero puede relevarlo. */
        'abandon_minutes' => (int) env('CRM_INBOX_SLA_ABANDON_MINUTES', 30),
    ],

    /** Casos en atención que un analista puede llevar a la vez. 0 lo desactiva. */
    'max_active_per_analyst' => (int) env('CRM_INBOX_MAX_ACTIVE', 8),

    /**
     * Reintento automático de lo que no salió por WhatsApp. Solo mensajes recientes: un texto
     * que llega una hora tarde confunde más que ayuda; ese lo reintenta el analista a mano.
     */
    'retry' => [
        'window_minutes' => (int) env('CRM_INBOX_RETRY_WINDOW_MINUTES', 15),
        'max_attempts' => (int) env('CRM_INBOX_RETRY_MAX_ATTEMPTS', 3),
        'batch' => (int) env('CRM_INBOX_RETRY_BATCH', 20),
    ],

    /** Minutos sin señal de la bandeja tras los que un analista cuenta como desconectado. */
    'presence_offline_minutes' => (int) env('CRM_INBOX_PRESENCE_OFFLINE_MINUTES', 5),

    /** Horas que se guarda una propuesta calculada y nunca enviada antes de borrar el PDF. */
    'draft_ttl_hours' => (int) env('CRM_INBOX_DRAFT_TTL_HOURS', 24),

    /** Moneda en que SolIA cotiza. Se antepone al monto de la propuesta. */
    'quote_currency' => env('CRM_INBOX_QUOTE_CURRENCY', 'USD'),

    /**
     * Respuestas de respaldo: solo se usan mientras el supervisor no haya cargado ninguna
     * en la Biblioteca de atención. No se envían solas.
     * {cliente} es el primer nombre del cliente; {propuesta}, el número y monto de la cotización.
     * Un texto con {propuesta} solo se ofrece si el caso trae cotización.
     */
    'quick_replies' => [
        ['label' => 'Pedir cédula', 'text' => '{cliente}, ¿me confirmas la cédula del titular para revisar tu ficha?'],
        ['label' => 'Revisar propuesta', 'text' => 'Tengo aquí tu propuesta {propuesta}. ¿La revisamos juntos?'],
        ['label' => 'Un momento', 'text' => 'Dame un momento, {cliente}. Ya estoy revisando tu caso.'],
    ],

];
