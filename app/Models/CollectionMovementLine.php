<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea del desglose de un aviso de cobro causada por un alta o una baja:
 * «Ajuste por exclusión de JUAN PÉREZ (15/02, 45 días) −US$ 50,00».
 */
class CollectionMovementLine extends Model
{
    public const KIND_PRORATION = 'PRORRATEO';

    public const KIND_PERIOD = 'CUOTA';

    public const KIND_CREDIT = 'CREDITO';

    public const KIND_CHARGE = 'AVISO';

    public const KIND_REVERSAL = 'REVERSO';

    protected $table = 'collection_movement_lines';

    /**
     * @var list<string>
     */
    protected $fillable = ['collection_id', 'affiliate_movement_id', 'kind', 'description', 'amount', 'reverted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'reverted' => 'boolean'];
    }

    /**
     * @return BelongsTo<AffiliateMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(AffiliateMovement::class, 'affiliate_movement_id');
    }

    /**
     * @return BelongsTo<Collection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
