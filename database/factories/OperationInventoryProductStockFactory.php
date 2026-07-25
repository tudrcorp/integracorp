<?php

namespace Database\Factories;

use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductStock;
use App\Models\OperationInventoryUbication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationInventoryProductStock>
 */
class OperationInventoryProductStockFactory extends Factory
{
    protected $model = OperationInventoryProductStock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operation_inventory_product_id' => OperationInventoryProduct::factory(),
            'operation_inventory_ubication_id' => OperationInventoryUbication::query()->value('id') ?? 1,
            'existence' => fake()->numberBetween(0, 100),
            'created_by' => 'system',
            'updated_by' => 'system',
        ];
    }
}
