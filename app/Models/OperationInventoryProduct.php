<?php

namespace App\Models;

use App\Enums\OperationInventoryProductPresentation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationInventoryProduct extends Model
{
    /** @use HasFactory<\Database\Factories\OperationInventoryProductFactory> */
    use HasFactory;

    protected $table = 'operation_inventory_products';

    protected $fillable = [
        'operation_inventory_product_category_id',
        'code',
        'name',
        'cost',
        'unit',
        'presentation',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'presentation' => OperationInventoryProductPresentation::class,
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryProductCategory::class, 'operation_inventory_product_category_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(OperationInventoryProductStock::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(OperationInventory::class);
    }

    public function inventoryEntries(): HasMany
    {
        return $this->hasMany(OperationInventoryEntry::class);
    }

    public function inventoryOutflows(): HasMany
    {
        return $this->hasMany(OperationInventoryOutflow::class);
    }

    public function totalExistence(): int
    {
        return (int) $this->stocks()->sum('existence');
    }
}
