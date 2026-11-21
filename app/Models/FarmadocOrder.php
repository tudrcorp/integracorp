<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    public const STATUS_ACCEPTED = 'aceptado';

    public const STATUS_SENT = 'enviado';

    public const STATUS_COMPLETED = 'finalizado';

    /** Estatus propio de IntegraCorp: el pedido ya entró al inventario. Farmadoc lo consulta en nuestro API. */
    public const STATUS_RECEIVED = 'registrado-inventario';

    /** Prefijo del número de la nota de recibido y aprobado. */
    public const RECEIPT_NUMBER_PREFIX = 'NRA-';

    /** Origen que se muestra cuando el pedido no salió de una coordinación. */
    public const DEFAULT_ORIGIN_LABEL = 'Dpto. Operaciones TDG';

    /** Largo de la columna `status`. */
    public const STATUS_MAX_LENGTH = 30;

    /** @var array<string, true> columnas ya confirmadas (solo se cachea el «sí»). */
    private static array $confirmedColumns = [];

    protected $table = 'farmadoc_orders';

    protected $fillable = [
        'order_number',
        'farmadoc_order_id',
        'farmadoc_service_order_id',
        'service_order_number',
        'operation_coordination_service_id',
        'operation_inventory_ubication_id',
        'coordination_reference',
        'patient_name',
        'items',
        'items_count',
        'units_count',
        'status',
        'farmadoc_updated_at',
        'delivery_note_number',
        'accepted_at',
        'accepted_by',
        'acceptance_items',
        'receipt_number',
        'received_at',
        'received_by_user_id',
        'received_by_name',
        'receipt_ubication_id',
        'receipt_items',
        'receipt_notes',
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
            'farmadoc_service_order_id' => 'integer',
            'farmadoc_updated_at' => 'datetime',
            'accepted_at' => 'datetime',
            'acceptance_items' => 'array',
            'operation_inventory_ubication_id' => 'integer',
            'received_at' => 'datetime',
            'received_by_user_id' => 'integer',
            'receipt_ubication_id' => 'integer',
            'receipt_items' => 'array',
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
     * Ubicación del inventario que pidió los medicamentos (DIAGNO-MOVIL o DIAGNO-CENTER).
     *
     * @return BelongsTo<OperationInventoryUbication, $this>
     */
    public function ubication(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryUbication::class, 'operation_inventory_ubication_id');
    }

    /**
     * Ubicación donde se dio entrada al pedido.
     *
     * @return BelongsTo<OperationInventoryUbication, $this>
     */
    public function receiptUbication(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryUbication::class, 'receipt_ubication_id');
    }

    /**
     * @return HasMany<FarmadocOrderAcceptanceDismissal, $this>
     */
    public function acceptanceDismissals(): HasMany
    {
        return $this->hasMany(FarmadocOrderAcceptanceDismissal::class, 'farmadoc_order_id');
    }

    /**
     * Pedidos que todavía pueden cambiar en Farmadoc. Un estatus que no conocemos
     * cuenta como abierto: se sigue consultando hasta llegar a uno de cierre.
     *
     * @param  Builder<FarmadocOrder>  $query
     * @return Builder<FarmadocOrder>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(fn (Builder $open): Builder => $open
            ->whereNull('status')
            ->orWhereNotIn('status', self::closedStatuses()));
    }

    /**
     * @return list<string>
     */
    public static function openStatuses(): array
    {
        return [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_ACCEPTED];
    }

    /**
     * Estatus en los que Farmadoc ya no cambia el pedido: se dejan de consultar.
     *
     * @return list<string>
     */
    public static function closedStatuses(): array
    {
        return [self::STATUS_SENT, self::STATUS_COMPLETED, self::STATUS_RECEIVED];
    }

    /**
     * Farmadoc ya despachó el pedido y todavía no se le dio entrada al inventario.
     */
    public function canBeReceived(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_COMPLETED], true)
            && $this->received_at === null
            && self::receiptColumnsExist();
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED || $this->received_at !== null;
    }

    public static function receiptNumberFor(int $orderId): string
    {
        return self::RECEIPT_NUMBER_PREFIX.str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, self::closedStatuses(), true);
    }

    /**
     * Estatus en los que Farmadoc ya generó la nota de entrega del pedido.
     *
     * @return list<string>
     */
    public static function deliveryNoteStatuses(): array
    {
        return [self::STATUS_ACCEPTED, self::STATUS_SENT, self::STATUS_COMPLETED, self::STATUS_RECEIVED];
    }

    /**
     * Si el pedido tiene nota de entrega en Farmadoc: ya fue aceptado (o enviado) o
     * Farmadoc nos informó el número de la nota.
     */
    public function hasDeliveryNote(): bool
    {
        return filled($this->delivery_note_number)
            || in_array($this->status, self::deliveryNoteStatuses(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_IN_PROGRESS => 'En proceso',
            self::STATUS_ACCEPTED => 'Aceptado',
            self::STATUS_SENT => 'Enviado',
            self::STATUS_COMPLETED => 'Finalizado',
            self::STATUS_RECEIVED => 'Registrado en inventario',
        ];
    }

    /**
     * Etiqueta del estatus. Uno que no conocemos se muestra tal como lo manda Farmadoc
     * («por-despachar» → «Por despachar»), nunca se esconde como «Pendiente».
     */
    public static function statusLabel(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'Desconocido';
        }

        return self::statusOptions()[$status] ?? Str::ucfirst(str_replace(['-', '_'], ' ', $status));
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_IN_PROGRESS => 'info',
            self::STATUS_ACCEPTED => 'success',
            self::STATUS_SENT => 'primary',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_RECEIVED => 'success',
            default => 'gray',
        };
    }

    /**
     * Estatus tal como lo informa Farmadoc, en forma de slug («Aceptado» → «aceptado»).
     * Solo cae en «pendiente» si viene vacío o no es texto.
     */
    public static function normalizeStatus(mixed $status): string
    {
        if (! is_string($status)) {
            return self::STATUS_PENDING;
        }

        $slug = Str::limit(Str::slug($status), self::STATUS_MAX_LENGTH, '');

        return trim($slug, '-') === '' ? self::STATUS_PENDING : trim($slug, '-');
    }

    /**
     * Si ya corrió la migración que agrega la nota de entrega y la fecha de aceptación.
     */
    public static function acceptanceColumnsExist(): bool
    {
        return self::hasColumn('delivery_note_number') && self::hasColumn('accepted_at');
    }

    /**
     * Si ya corrió la migración que agrega quién aceptó y el detalle por medicamento.
     */
    public static function acceptanceDetailColumnsExist(): bool
    {
        return self::hasColumn('accepted_by') && self::hasColumn('acceptance_items');
    }

    /**
     * Si ya corrió la migración de la entrada a inventario y la ubicación de destino.
     */
    public static function receiptColumnsExist(): bool
    {
        return self::hasColumn('receipt_items') && self::hasColumn('operation_inventory_ubication_id');
    }

    /**
     * Las columnas nuevas llegan por migraciones que se corren aparte: se confirma cada
     * una antes de escribirla, para que una migración pendiente nunca rompa la consulta.
     */
    public static function hasColumn(string $column): bool
    {
        if (isset(self::$confirmedColumns[$column])) {
            return true;
        }

        if (! Schema::hasColumn((new self)->getTable(), $column)) {
            return false;
        }

        self::$confirmedColumns[$column] = true;

        return true;
    }
}
