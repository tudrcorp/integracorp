<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un hecho de la traza cotización → orden de servicio. Solo se inserta.
 *
 * @see \App\Support\Operations\ServiceOrderFlowTrace
 */
class OperationServiceFlowEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'operation_service_flow_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'operation_coordination_service_id',
        'telemedicine_case_id',
        'operation_service_order_id',
        'operation_quote_generator_id',
        'event',
        'description',
        'reason',
        'changes',
        'actor_id',
        'actor_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OperationServiceOrder, $this>
     */
    public function operationServiceOrder(): BelongsTo
    {
        return $this->belongsTo(OperationServiceOrder::class);
    }

    /**
     * @return BelongsTo<OperationQuoteGenerator, $this>
     */
    public function operationQuoteGenerator(): BelongsTo
    {
        return $this->belongsTo(OperationQuoteGenerator::class);
    }
}
