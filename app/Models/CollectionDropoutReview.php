<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Decisión sobre una cuota pendiente de una afiliación dada de baja. Una fila activa
 * (`reverted_at` nulo) por cuota; revertir cierra la fila en vez de borrarla.
 */
class CollectionDropoutReview extends Model
{
    public const DECISION_AUTO_CANCELLED = 'anulada_por_baja';

    public const DECISION_CANCELLED = 'anulada_por_analista';

    public const DECISION_KEPT = 'mantenida_como_deuda';

    protected $table = 'collection_dropout_reviews';

    protected $fillable = [
        'collection_id',
        'affiliation_code',
        'affiliation_type',
        'affiliation_status',
        'decision',
        'status_before',
        'status_after',
        'amount',
        'due_date',
        'batch_uuid',
        'adjustment_id',
        'note',
        'decided_by_id',
        'decided_by_name',
        'reverted_at',
        'reverted_by_id',
        'reverted_by_name',
        'revert_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'reverted_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function decisionLabels(): array
    {
        return [
            self::DECISION_AUTO_CANCELLED => 'Anulada automáticamente por la baja',
            self::DECISION_CANCELLED => 'Anulada por el analista',
            self::DECISION_KEPT => 'Mantenida como deuda',
        ];
    }

    public function decisionLabel(): string
    {
        return self::decisionLabels()[$this->decision] ?? (string) $this->decision;
    }

    public function isCancellation(): bool
    {
        return in_array($this->decision, [self::DECISION_AUTO_CANCELLED, self::DECISION_CANCELLED], true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('reverted_at');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
