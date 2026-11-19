<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido de medicamentos enviado a Farmadoc. El estatus vive en Farmadoc;
 * aquí se guarda el último conocido para listar sin consultar el API.
 */
class FarmadocOrder extends Model
{
    /** @use HasFactory<\Database\Factories\FarmadocOrderFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pendiente';

    public const STATUS_IN_PROGRESS = 'en-proceso';

    public const STATUS_COMPLETED = 'finalizado';

    protected $table = 'farmadoc_orders';

    protected $fillable = [
        'order_number',
        'farmadoc_order_id',
        'operation_coordination_service_id',
        'coordination_reference',
        'patient_name',
        'items',
        'items_count',
        'units_count',
        'status',
        'farmadoc_updated_at',
        'status_checked_at',
        'status_check_error',
        'created_by_user_id',
        'created_by_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'items_count' => 'integer',
            'units_count' => 'integer',
            'farmadoc_order_id' => 'integer',
            'farmadoc_updated_at' => 'datetime',
            'status_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OperationCoordinationService, $this>
     */
    public function coordination(): BelongsTo
    {
        return $this->belongsTo(OperationCoordinationService::class, 'operation_coordination_service_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @param  Builder<FarmadocOrder>  $query
     * @return Builder<FarmadocOrder>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::openStatuses());
    }

    /**
     * @return list<string>
     */
    public static function openStatuses(): array
    {
        return [self::STATUS_PENDING, self::STATUS_IN_PROGRESS];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::openStatuses(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_IN_PROGRESS => 'En proceso',
            self::STATUS_COMPLETED => 'Finalizado',
        ];
    }

    public static function statusLabel(?string $status): string
    {
        return self::statusOptions()[$status] ?? 'Desconocido';
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_IN_PROGRESS => 'info',
            self::STATUS_COMPLETED => 'success',
            default => 'gray',
        };
    }

    public static function normalizeStatus(mixed $status): string
    {
        return is_string($status) && array_key_exists($status, self::statusOptions())
            ? $status
            : self::STATUS_PENDING;
    }
}
