<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Huella de una cotización borrada (individual o corporativa). Se escribe en el evento
 * `deleting` del modelo, así cubre cualquier camino de borrado que pase por Eloquent.
 *
 * @property string $quote_type
 * @property int $quote_id
 * @property list<int>|null $plan_ids
 */
class QuoteDeletionLog extends Model
{
    public const INDIVIDUAL = 'INDIVIDUAL';

    public const CORPORATE = 'CORPORATIVA';

    protected $fillable = [
        'quote_type',
        'quote_id',
        'code',
        'plan',
        'plan_ids',
        'status',
        'agent_id',
        'code_agency',
        'quote_created_at',
        'deleted_by_id',
        'deleted_by_name',
        'deleted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_ids' => 'array',
            'quote_created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @param  list<int>  $planIds  planes del detalle de la cotización
     */
    public static function record(string $type, Model $quote, array $planIds): self
    {
        $user = Auth::user();

        return static::query()->create([
            'quote_type' => $type,
            'quote_id' => (int) $quote->getKey(),
            'code' => $quote->getAttribute('code'),
            'plan' => $quote->getAttribute('plan') !== null ? (string) $quote->getAttribute('plan') : null,
            'plan_ids' => array_values(array_unique(array_map('intval', $planIds))),
            'status' => $quote->getAttribute('status'),
            'agent_id' => $quote->getAttribute('agent_id'),
            'code_agency' => $quote->getAttribute('code_agency'),
            'quote_created_at' => $quote->getAttribute('created_at'),
            'deleted_by_id' => $user?->getAuthIdentifier(),
            'deleted_by_name' => $user?->name,
            'deleted_at' => now(),
        ]);
    }
}
