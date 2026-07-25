<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationInventoryProductCategory extends Model
{
    /** @use HasFactory<\Database\Factories\OperationInventoryProductCategoryFactory> */
    use HasFactory;

    protected $table = 'operation_inventory_product_categories';

    protected $fillable = [
        'name',
        'description',
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

    public function products(): HasMany
    {
        return $this->hasMany(OperationInventoryProduct::class);
    }
}
