<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationInventoryUbication extends Model
{
    protected $table = 'operation_inventory_ubications';

    protected $fillable = [
        'name',
        'address',
        'state_id',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function operationInventories(): HasMany
    {
        return $this->hasMany(OperationInventory::class);
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(OperationInventoryProductStock::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(OperationInventory::class, 'operation_inventory_ubication_id');
    }

    public function inventoryEntries(): HasMany
    {
        return $this->hasMany(OperationInventoryEntry::class, 'operation_inventory_ubication_id');
    }

    public function inventoryOutflows(): HasMany
    {
        return $this->hasMany(OperationInventoryOutflow::class, 'operation_inventory_ubication_id');
    }
}
