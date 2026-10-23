<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ajuste manual de una cuota de cobranza (bitácora inmutable).
 *
 * @property array<string, array{before: mixed, after: mixed}> $changes
 */
class CollectionAdjustment extends Model
{
    public const MODE_SINGLE = 'individual';

    public const MODE_BULK = 'masivo';

    /** Reprogramado por una corrección de fecha de vigencia (o su reversión). */
    public const MODE_EFFECTIVE_DATE = 'vigencia';

    /** Anulada o restituida por la baja (o la reactivación) de su afiliación, o por la decisión del analista sobre esas cuotas. */
    public const MODE_DROPOUT = 'baja';

    protected $table = 'collection_adjustments';

    protected $fillable = [
        'collection_id',
        'affiliation_code',
        'collection_invoice_number',
        'batch_uuid',
        'mode',
        'changes',
        'reason',
        'performed_by_id',
        'performed_by_name',
        'ip',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }
}
