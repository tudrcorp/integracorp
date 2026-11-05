<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cierre mensual del capitado de un proveedor Outsourcing: la foto exacta con la que se
 * pre-factura ese mes. Mientras está CERRADO, el reporte del mes sale de aquí y no se recalcula.
 */
class CapitationBillingClosing extends Model
{
    public const STATUS_CLOSED = 'CERRADO';

    public const STATUS_REOPENED = 'REABIERTO';

    protected $table = 'capitation_billing_closings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period' => 'immutable_date',
            'discounts' => 'array',
            'monthly_amount' => 'decimal:2',
            'prorate_amount' => 'decimal:2',
            'discounts_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'bcv_rate' => 'decimal:4',
            'total_ves' => 'decimal:2',
            'closed_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
        ];
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CapitationBillingClosingItem::class, 'closing_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CapitationBillingClosingEvent::class, 'closing_id')->orderByDesc('id');
    }
}
