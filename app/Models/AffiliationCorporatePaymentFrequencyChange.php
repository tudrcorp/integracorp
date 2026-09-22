<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro inmutable de un cambio de frecuencia de pago de una afiliación
 * corporativa: quién, cuándo, el estado anterior completo (para poder
 * revertirlo), los avisos cancelados y creados, y a quién se notificó.
 *
 * @property array<int, array<string, mixed>>|null $cancelled_collections
 * @property array<int, array<string, mixed>>|null $created_collections
 * @property array<string, mixed>|null $snapshot
 * @property array<string, mixed>|null $notification_log
 */
class AffiliationCorporatePaymentFrequencyChange extends Model
{
    public const STATUS_APPLIED = 'APLICADO';

    public const STATUS_REVERSED = 'REVERSADO';

    protected $table = 'affiliation_corporate_payment_frequency_changes';

    protected $fillable = [
        'batch_uuid',
        'affiliation_corporate_id',
        'affiliation_code',
        'affiliation_name',
        'previous_frequency',
        'new_frequency',
        'fee_anual',
        'previous_total_amount',
        'new_total_amount',
        'pending_balance',
        'cancelled_collections',
        'created_collections',
        'snapshot',
        'status',
        'performed_by_id',
        'performed_by_name',
        'performed_from',
        'ip',
        'user_agent',
        'notification_log',
        'validated_at',
        'validated_by_id',
        'validated_by_name',
        'reversed_at',
        'reversed_by_id',
        'reversed_by_name',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'fee_anual' => 'decimal:2',
            'previous_total_amount' => 'decimal:2',
            'new_total_amount' => 'decimal:2',
            'pending_balance' => 'decimal:2',
            'cancelled_collections' => 'array',
            'created_collections' => 'array',
            'snapshot' => 'array',
            'notification_log' => 'array',
            'validated_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function affiliationCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_id');
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function isValidated(): bool
    {
        return $this->validated_at !== null;
    }

    /**
     * Estado para mostrar: Revertido gana sobre Validado.
     */
    public function displayStatus(): string
    {
        if ($this->isReversed()) {
            return 'Revertido';
        }

        return $this->isValidated() ? 'Validado' : 'Por validar';
    }
}
