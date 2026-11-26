<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Factura al paciente (no fiscal) por lo NO CUBIERTO: medicamentos despachados desde el
 * inventario Diagnomóvil, o laboratorios, estudios y consultas con especialista.
 */
class OperationMedicationInvoice extends Model
{
    /** @use HasFactory<\Database\Factories\OperationMedicationInvoiceFactory> */
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'PENDIENTE_PAGO';

    public const STATUS_PAID = 'PAGADA';

    public const STATUS_ANNULLED = 'ANULADA';

    /** Correlativo de las facturas emitidas desde el 07/11/2026; las anteriores conservan «FMP-». */
    public const NUMBER_PREFIX = 'FAC-';

    public const TYPE_MEDICATIONS = 'MEDICAMENTOS';

    public const TYPE_LABORATORIES = 'LABORATORIOS';

    public const TYPE_IMAGING = 'IMAGENOLOGIA';

    public const TYPE_SPECIALIST = 'ESPECIALISTA';

    /** Responsable de pago: el propio paciente. */
    public const PAYER_PATIENT = 'PACIENTE';

    /** Responsable de pago: el grupo corporativo (o la empresa de Nuevos Negocios) dueño de la afiliación. */
    public const PAYER_CORPORATE = 'CORPORATIVO';

    protected $table = 'operation_medication_invoices';

    protected $fillable = [
        'invoice_number',
        'manual_invoice_number',
        'manual_invoice_number_set_at',
        'manual_invoice_number_set_by_name',
        'operation_coordination_service_id',
        'telemedicine_patient_id',
        'telemedicine_case_id',
        'patient_name',
        'patient_identification',
        'case_code',
        'claim_number',
        'payer_type',
        'payer_name',
        'payer_identification',
        'payer_address',
        'payer_phone',
        'payer_email',
        'status',
        'invoice_type',
        'operation_service_order_id',
        'operation_quote_generator_id',
        'total_usd',
        'bcv_rate',
        'total_ves',
        'issued_at',
        'issued_by_user_id',
        'issued_by_name',
        'paid_at',
        'payment_method',
        'payment_reference',
        'payment_notes',
        'payment_registered_at',
        'payment_registered_by_name',
        'annulled_at',
        'annulled_by_name',
        'annulment_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_usd' => 'decimal:2',
            'bcv_rate' => 'decimal:4',
            'total_ves' => 'decimal:2',
            'issued_at' => 'datetime',
            'manual_invoice_number_set_at' => 'datetime',
            'paid_at' => 'date',
            'payment_registered_at' => 'datetime',
            'annulled_at' => 'datetime',
        ];
    }

    /** Largo máximo del número de factura registrado a mano. */
    public const MANUAL_NUMBER_MAX = 40;

    /**
     * Número que ve todo el mundo: el registrado a mano por el analista o, si aún no hay, el
     * interno del sistema (FAC-000001). El interno no cambia: la bitácora y el inventario lo citan.
     */
    public function displayNumber(): string
    {
        return filled($this->manual_invoice_number) ? (string) $this->manual_invoice_number : (string) $this->invoice_number;
    }

    public function hasManualNumber(): bool
    {
        return filled($this->manual_invoice_number);
    }

    /** Condiciones de pago de toda factura a pacientes. */
    public const PAYMENT_TERMS = 'CONTADO';

    /** Días entre la emisión y el vencimiento: la factura es de contado y vence el mismo día. */
    public const DUE_DAYS = 0;

    public function dueDate(): ?Carbon
    {
        return $this->issued_at?->copy()->addDays(self::DUE_DAYS);
    }

    public static function formatNumber(int $id): string
    {
        return self::NUMBER_PREFIX.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_MEDICATIONS => 'Medicamentos',
            self::TYPE_LABORATORIES => 'Laboratorios',
            self::TYPE_IMAGING => 'Estudios de imagen',
            self::TYPE_SPECIALIST => 'Consulta con especialista',
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return self::typeOptions()[(string) $type] ?? self::typeOptions()[self::TYPE_MEDICATIONS];
    }

    public function isMedications(): bool
    {
        return ($this->invoice_type ?? self::TYPE_MEDICATIONS) === self::TYPE_MEDICATIONS;
    }

    /**
     * Factura nacida al aprobar una cotización: el proveedor entrega, no el inventario, así que
     * anularla nunca devuelve existencia.
     */
    public function isFromQuote(): bool
    {
        return filled($this->operation_quote_generator_id);
    }

    /**
     * @return array<string, string>
     */
    public static function payerOptions(): array
    {
        return [
            self::PAYER_PATIENT => 'El paciente',
            self::PAYER_CORPORATE => 'El grupo corporativo',
        ];
    }

    public static function payerLabel(?string $type): ?string
    {
        return self::payerOptions()[(string) $type] ?? null;
    }

    public function isPendingPayment(): bool
    {
        return $this->status === self::STATUS_PENDING_PAYMENT;
    }

    /**
     * @return HasMany<OperationMedicationInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OperationMedicationInvoiceItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<OperationMedicationInvoiceEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OperationMedicationInvoiceEvent::class)->orderBy('id');
    }

    /**
     * @return HasOne<OperationAccountsReceivable, $this>
     */
    public function accountsReceivable(): HasOne
    {
        return $this->hasOne(OperationAccountsReceivable::class);
    }

    /**
     * @return BelongsTo<OperationServiceOrder, $this>
     */
    public function operationServiceOrder(): BelongsTo
    {
        return $this->belongsTo(OperationServiceOrder::class);
    }

    /**
     * @return BelongsTo<OperationQuoteGenerator, $this>
     */
    public function operationQuoteGenerator(): BelongsTo
    {
        return $this->belongsTo(OperationQuoteGenerator::class);
    }

    /**
     * @return BelongsTo<OperationCoordinationService, $this>
     */
    public function operationCoordinationService(): BelongsTo
    {
        return $this->belongsTo(OperationCoordinationService::class);
    }

    /**
     * @return BelongsTo<TelemedicinePatient, $this>
     */
    public function telemedicinePatient(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatient::class);
    }

    /**
     * @return BelongsTo<TelemedicineCase, $this>
     */
    public function telemedicineCase(): BelongsTo
    {
        return $this->belongsTo(TelemedicineCase::class);
    }
}
