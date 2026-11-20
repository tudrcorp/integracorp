<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Con qué versión de diseño se emitió un plan de una cotización (individual o corporativa).
 * Regenerar la propuesta reutiliza esta versión, así un PDF ya enviado no cambia de aspecto.
 */
class QuoteDesignIssuance extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'quote_design_issuances';

    protected $fillable = [
        'scope',
        'quote_id',
        'plan_id',
        'quote_design_version_id',
    ];

    /**
     * @return BelongsTo<QuoteDesignVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(QuoteDesignVersion::class, 'quote_design_version_id');
    }
}
