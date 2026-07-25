<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationInventory extends Model
{
    protected $table = 'operation_inventories';

    protected $fillable = [
        'operation_inventory_product_id',
        'operation_inventory_ubication_id',
        'name',
        'unit',
        'operation_inventory_type_id',
        'existence',
        'cost',
        'ubication',
        'created_by',
        'updated_by',
        'operation_inventory_principle_active_id',
        'laboratory',
        'min_stock',
        'location',
        'is_active',
        'operation_inventory_category_id',
        'barcode',
        'concentration',
        'image',
        'is_covered',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'existence' => 'integer',
            'cost' => 'decimal:2',
            'is_active' => 'boolean',
            'is_covered' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryProduct::class, 'operation_inventory_product_id');
    }

    public function ubicationRelation(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryUbication::class, 'operation_inventory_ubication_id');
    }

    public function operationInventoryCategory(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryCategory::class);
    }

    public function operationInventoryEntries(): HasMany
    {
        return $this->hasMany(OperationInventoryEntry::class);
    }

    public function operationInventoryOutflows(): HasMany
    {
        return $this->hasMany(OperationInventoryOutflow::class);
    }

    public function operationInventoryType(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryType::class, 'operation_inventory_type_id', 'id');
    }

    public function operationInventoryPrincipleActive(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryPrincipleActive::class);
    }
}
