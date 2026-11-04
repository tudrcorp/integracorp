<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una orden o cita que se reasignó o canceló al eliminar un proveedor, con su «foto» previa.
 */
class ProviderDeletionLogItem extends Model
{
    public const KIND_ORDER = 'order';

    public const KIND_APPOINTMENT = 'appointment';

    public const RESTORED = 'RESTAURADA';

    public const SKIPPED = 'NO_DEVUELTA';

    protected $fillable = [
        'provider_deletion_log_id',
        'kind',
        'action',
        'operation_service_order_id',
        'operation_medical_appointment_id',
        'snapshot',
        'result',
        'restore_status',
        'restore_note',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'result' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ProviderDeletionLog, $this>
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(ProviderDeletionLog::class, 'provider_deletion_log_id');
    }
}
