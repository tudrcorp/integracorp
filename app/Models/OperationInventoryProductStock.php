<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationInventoryProductStock extends Model
{
    /** @use HasFactory<\Database\Factories\OperationInventoryProductStockFactory> */
    use HasFactory;

    protected $table = 'operation_inventory_product_stocks';

    protected $fillable = [
        'operation_inventory_product_id',
        'operation_inventory_ubication_id',
        'existence',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'existence' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryProduct::class, 'operation_inventory_product_id');
    }

    public function ubication(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryUbication::class, 'operation_inventory_ubication_id');
    }
}
