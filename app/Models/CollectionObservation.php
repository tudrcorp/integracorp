<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Observación de cobranza de una afiliación. La bitácora no se edita ni se
 * borra: cada nota queda con quién la escribió, cuándo y a qué cuota se refería.
 */
class CollectionObservation extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionObservationFactory> */
    use HasFactory;

    public const TYPE_INDIVIDUAL = 'INDIVIDUAL';

    public const TYPE_CORPORATE = 'CORPORATIVA';

    public const MAX_LENGTH = 2000;

    protected $fillable = [
        'affiliation_code',
        'affiliation_type',
        'collection_id',
        'due_date',
        'amount',
        'observation',
        'created_by',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
