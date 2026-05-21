<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationServiceOrderQuote extends Model
{
    protected $fillable = [
        'operation_service_order_id',
        'quote_number',
        'supplier_id',
        'supplier_external',
        'bcv_rate',
        'total_amount_usd',
        'total_amount_ves',
        'items_payload',
        'quote_pdf_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'items_payload' => 'array',
        ];
    }

    public function operationServiceOrder(): BelongsTo
    {
        return $this->belongsTo(OperationServiceOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
