<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una regeneración del aviso de cobro: lo que se imprimió (datos del cliente, emisión y período)
 * y quién lo hizo. Lo editado vale solo para el PDF; la cuota no cambia.
 */
class CollectionNoticeRegeneration extends Model
{
    protected $fillable = [
        'collection_id',
        'invoice_number',
        'client',
        'emission_date',
        'period_from',
        'period_to',
        'changed_fields',
        'user_id',
        'user_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client' => 'array',
            'changed_fields' => 'array',
            'emission_date' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Collection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
