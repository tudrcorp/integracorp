<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationInventoryOutflow extends Model
{
    protected $table = 'operation_inventory_outflows';

    protected $fillable = [
        'operation_inventory_id',
        'operation_inventory_product_id',
        'operation_inventory_ubication_id',
        'quantity',
        'operation_inventory_type_id',
        'created_by',
        'type_entry',
        'observations',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function operationInventory(): BelongsTo
    {
        return $this->belongsTo(OperationInventory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryProduct::class, 'operation_inventory_product_id');
    }

    public function ubication(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryUbication::class, 'operation_inventory_ubication_id');
    }

    public function operationInventoryType(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryType::class);
    }
}
