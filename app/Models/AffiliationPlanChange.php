<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un cambio de plan (o de frecuencia) de una afiliación individual, con el estado anterior
 * completo y lo que se anuló, creó o actualizó en la cobranza.
 */
class AffiliationPlanChange extends Model
{
    protected $fillable = [
        'batch_uuid',
        'affiliation_id',
        'affiliation_code',
        'previous_plan_id',
        'new_plan_id',
        'previous_coverage_id',
        'new_coverage_id',
        'previous_frequency',
        'new_frequency',
        'previous_fee_anual',
        'new_fee_anual',
        'previous_total_amount',
        'new_total_amount',
        'affiliates',
        'cancelled_collections',
        'created_collections',
        'updated_collections',
        'snapshot',
        'reason',
        'exception_rule',
        'exception_note',
        'exception_affiliates',
        'performed_by_id',
        'performed_by_name',
        'performed_from',
        'ip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'affiliates' => 'array',
            'cancelled_collections' => 'array',
            'created_collections' => 'array',
            'updated_collections' => 'array',
            'snapshot' => 'array',
            'exception_affiliates' => 'array',
            'previous_fee_anual' => 'decimal:2',
            'new_fee_anual' => 'decimal:2',
            'previous_total_amount' => 'decimal:2',
            'new_total_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Affiliation, $this>
     */
    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class);
    }
}
