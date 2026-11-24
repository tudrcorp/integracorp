<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje de WhatsApp recibido por el webhook de UltraMsg. El id del mensaje es único:
 * UltraMsg puede repetir un aviso y no se procesa dos veces.
 */
class WhatsAppInboundMessage extends Model
{
    /** Respuesta SI/NO válida, guardada en la notificación. */
    public const RESULT_ANSWER = 'RESPUESTA';

    /** El número tenía una pregunta pendiente pero el texto no era SI ni NO. */
    public const RESULT_INVALID = 'RESPUESTA_INVALIDA';

    /** El número no tenía ninguna pregunta pendiente. */
    public const RESULT_IGNORED = 'IGNORADO';

    /** Mensaje propio, de grupo o que no es texto. */
    public const RESULT_SKIPPED = 'DESCARTADO';

    protected $table = 'whatsapp_inbound_messages';

    protected $fillable = [
        'provider',
        'message_id',
        'event_type',
        'phone',
        'message_type',
        'body',
        'result',
        'service_order_patient_notification_id',
        'payload',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function serviceOrderPatientNotification(): BelongsTo
    {
        return $this->belongsTo(ServiceOrderPatientNotification::class);
    }
}
