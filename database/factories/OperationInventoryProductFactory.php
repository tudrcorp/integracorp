<?php

namespace Database\Factories;

use App\Enums\OperationInventoryProductPresentation;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationInventoryProduct>
 */
class OperationInventoryProductFactory extends Factory
{
    protected $model = OperationInventoryProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operation_inventory_product_category_id' => OperationInventoryProductCategory::factory(),
            'code' => strtoupper(fake()->unique()->bothify('PROD-####')),
            'name' => strtoupper(fake()->words(3, true)),
            'cost' => fake()->randomFloat(2, 0.5, 500),
            'unit' => fake()->randomElement(['UNIDAD', 'TABLETAS', 'GOTAS', 'AMPOLLAS', 'M.L.']),
            'presentation' => fake()->randomElement(OperationInventoryProductPresentation::cases())->value,
            'is_active' => true,
            'created_by' => 'system',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
