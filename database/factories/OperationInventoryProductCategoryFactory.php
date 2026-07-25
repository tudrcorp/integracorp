<?php

namespace Database\Factories;

use App\Models\OperationInventoryProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationInventoryProductCategory>
 */
class OperationInventoryProductCategoryFactory extends Factory
{
    protected $model = OperationInventoryProductCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
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
