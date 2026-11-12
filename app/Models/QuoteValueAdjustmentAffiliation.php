<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Población contratada por cotización que todavía no está cargada como afiliados: personas y
 * tarifa anual que se suman a los totales de la afiliación corporativa hasta `valid_until`
 * (fin del año de afiliación en curso; la renovación vuelve a cotizar por catálogo).
 */
class QuoteValueAdjustmentAffiliation extends Model
{
    protected $table = 'quote_value_adjustment_affiliations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'quote_value_adjustment_id', 'affiliation_type', 'affiliation_id', 'affiliation_code',
        'plan_row_id', 'extra_persons', 'extra_annual', 'valid_until', 'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extra_persons' => 'integer',
            'extra_annual' => 'decimal:2',
            'valid_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<QuoteValueAdjustment, $this>
     */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(QuoteValueAdjustment::class, 'quote_value_adjustment_id');
    }
}
