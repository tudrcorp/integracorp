<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Alta o baja de un afiliado (individual o corporativo) con todo lo que movió en
 * afiliación, cobranza y operaciones, para auditarlo y revertirlo.
 */
class AffiliateMovement extends Model
{
    public const TYPE_INDIVIDUAL = 'INDIVIDUAL';

    public const TYPE_CORPORATE = 'CORPORATIVO';

    public const KIND_ADD = 'ALTA';

    public const KIND_REMOVE = 'BAJA';

    public const STATUS_APPLIED = 'APLICADO';

    public const STATUS_REVERTED = 'REVERTIDO';

    protected $table = 'affiliate_movements';

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'annual_fee' => 'decimal:2',
            'period_fee' => 'decimal:2',
            'prorated_amount' => 'decimal:2',
            'pending_credit' => 'decimal:2',
            'snapshot' => 'array',
            'effects' => 'array',
            'revert_effects' => 'array',
            'reverted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CollectionMovementLine, $this>
     */
    public function collectionLines(): HasMany
    {
        return $this->hasMany(CollectionMovementLine::class);
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function isAddition(): bool
    {
        return $this->kind === self::KIND_ADD;
    }

    public function isCorporate(): bool
    {
        return $this->affiliation_type === self::TYPE_CORPORATE;
    }

    public function kindLabel(): string
    {
        return $this->isAddition() ? 'Inclusión' : 'Exclusión';
    }
}
