<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * WhatsApp al paciente (o a su representante) cuando nace la orden de servicio de un
 * servicio no cubierto, con la pregunta SI/NO y la respuesta.
 *
 * A un mismo teléfono se le hace una sola pregunta a la vez: las demás esperan su turno
 * en EN_ESPERA. Así cada respuesta corresponde sin duda a una orden.
 *
 * Un mensaje de prueba (`is_test`) se envía desde Operaciones → «WhatsApp al paciente» a un
 * número cualquiera, con una orden real de muestra (`sample_order_id`), y recorre el mismo
 * camino: cola, PDF, UltraMsg y respuesta por el webhook.
 *
 * @see \App\Support\Operations\PatientWhatsApp\ServiceOrderPatientNotifier
 */
class ServiceOrderPatientNotification extends Model
{
    /** Esperando a que el paciente responda una orden anterior enviada al mismo teléfono. */
    public const STATUS_WAITING_TURN = 'EN_ESPERA';

    /** El envío está en cola. */
    public const STATUS_SENDING = 'ENVIANDO';

    /** Enviada: esperando la respuesta SI/NO. */
    public const STATUS_SENT = 'ENVIADA';

    public const STATUS_ANSWERED = 'RESPONDIDA';

    public const STATUS_NO_ANSWER = 'SIN_RESPUESTA';

    public const STATUS_FAILED = 'FALLIDA';

    public const STATUS_NO_PHONE = 'SIN_TELEFONO';

    /** El Centro de notificaciones tiene apagado este tipo de mensaje. */
    public const STATUS_BLOCKED = 'BLOQUEADA';

    public const ANSWER_YES = 'SI';

    public const ANSWER_NO = 'NO';

    public const RECIPIENT_PATIENT = 'PACIENTE';

    public const RECIPIENT_REPRESENTATIVE = 'REPRESENTANTE';

    /** Ocupan el turno del teléfono: mientras exista una así, las demás esperan. */
    public const TURN_HOLDING_STATUSES = [self::STATUS_SENDING, self::STATUS_SENT];

    /** Se pueden reenviar a mano. */
    public const RESENDABLE_STATUSES = [self::STATUS_NO_ANSWER, self::STATUS_FAILED, self::STATUS_NO_PHONE, self::STATUS_BLOCKED];

    /** UltraMsg lo tiene en su cola, todavía no sale hacia WhatsApp. */
    public const DELIVERY_PENDING = 'PENDIENTE';

    /** ✓ WhatsApp lo recibió, aún no llega al teléfono (apagado o sin señal). */
    public const DELIVERY_SERVER = 'ENVIADO';

    /** ✓✓ Llegó al teléfono del paciente. */
    public const DELIVERY_DEVICE = 'ENTREGADO';

    /** ✓✓ azul: lo abrió. Solo llega si el paciente tiene activas las confirmaciones de lectura. */
    public const DELIVERY_READ = 'LEIDO';

    /** Orden de avance: la entrega nunca retrocede aunque los avisos lleguen repetidos o en desorden. */
    public const DELIVERY_RANKS = [
        self::DELIVERY_PENDING => 1,
        self::DELIVERY_SERVER => 2,
        self::DELIVERY_DEVICE => 3,
        self::DELIVERY_READ => 4,
    ];

    protected $table = 'service_order_patient_notifications';

    protected $fillable = [
        'operation_service_order_id',
        'is_test',
        'sample_order_id',
        'operation_coordination_service_id',
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'order_number',
        'service_type',
        'patient_name',
        'recipient_name',
        'recipient_kind',
        'phone',
        'status',
        'message',
        'ultramsg_message_id',
        'delivery_status',
        'whatsapp_message_id',
        'attempts',
        'queued_at',
        'sent_at',
        'expires_at',
        'delivered_at',
        'read_at',
        'delivery_checked_at',
        'answer',
        'answer_raw',
        'answered_at',
        'invalid_replies',
        'error',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'attempts' => 'integer',
            'invalid_replies' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'answered_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'delivery_checked_at' => 'datetime',
        ];
    }

    public function operationServiceOrder(): BelongsTo
    {
        return $this->belongsTo(OperationServiceOrder::class);
    }

    /**
     * La orden que se envía: la propia o, en un mensaje de prueba, la que se usó de muestra.
     */
    public function documentOrderId(): ?int
    {
        $id = $this->is_test ? $this->sample_order_id : $this->operation_service_order_id;

        return $id !== null ? (int) $id : null;
    }

    public function telemedicinePatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    public function inboundMessages(): HasMany
    {
        return $this->hasMany(WhatsAppInboundMessage::class);
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_WAITING_TURN => 'En espera de turno',
            self::STATUS_SENDING => 'Enviando',
            self::STATUS_SENT => 'Enviada, esperando respuesta',
            self::STATUS_ANSWERED => 'Respondida',
            self::STATUS_NO_ANSWER => 'Sin respuesta',
            self::STATUS_FAILED => 'Fallida',
            self::STATUS_NO_PHONE => 'Sin teléfono válido',
            self::STATUS_BLOCKED => 'Bloqueada por el Centro de notificaciones',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? (string) $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_ANSWERED => $this->answer === self::ANSWER_NO ? 'danger' : 'success',
            self::STATUS_SENT, self::STATUS_SENDING => 'info',
            self::STATUS_WAITING_TURN => 'gray',
            default => 'warning',
        };
    }

    public static function deliveryRank(?string $deliveryStatus): int
    {
        return self::DELIVERY_RANKS[$deliveryStatus] ?? 0;
    }

    /**
     * ¿Ya salió hacia el paciente? Antes de eso no hay entrega que mostrar.
     */
    public function wasSent(): bool
    {
        return $this->sent_at !== null || $this->delivery_status !== null;
    }

    public function deliveryLabel(): ?string
    {
        if (! $this->wasSent()) {
            return null;
        }

        return match ($this->delivery_status) {
            self::DELIVERY_READ => '✓✓ Leído',
            self::DELIVERY_DEVICE => '✓✓ Entregado',
            self::DELIVERY_SERVER => '✓ Enviado, aún no llega al teléfono',
            default => 'Esperando confirmación de WhatsApp',
        };
    }

    public function deliveryColor(): string
    {
        return match ($this->delivery_status) {
            self::DELIVERY_READ => 'info',
            self::DELIVERY_DEVICE => 'success',
            self::DELIVERY_SERVER => 'warning',
            default => 'gray',
        };
    }

    /**
     * Cuándo llegó al teléfono (o cuándo lo leyó, si solo se supo eso).
     */
    public function deliveredMoment(): ?Carbon
    {
        return $this->delivered_at ?? $this->read_at;
    }

    public function isResendable(): bool
    {
        return ! $this->is_test && in_array($this->status, self::RESENDABLE_STATUSES, true);
    }
}
