<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Copia inmutable de un diseño de cotización tal como estaba al guardarse. Las propuestas
 * emitidas apuntan aquí ({@see QuoteDesignIssuance}), no al diseño editable.
 */
class QuoteDesignVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'quote_design_versions';

    protected $fillable = [
        'quote_design_id',
        'version',
        'snapshot',
        'fingerprint',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<QuoteDesign, $this>
     */
    public function design(): BelongsTo
    {
        return $this->belongsTo(QuoteDesign::class, 'quote_design_id');
    }
}
