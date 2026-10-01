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
