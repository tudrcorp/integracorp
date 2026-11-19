<?php

namespace Database\Factories;

use App\Models\FarmadocOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmadocOrder>
 */
class FarmadocOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);

        return [
            'order_number' => 'IC-0-'.now()->format('YmdHis').'-'.fake()->unique()->numberBetween(1000, 9999),
            'farmadoc_order_id' => fake()->numberBetween(1, 9999),
            'operation_coordination_service_id' => null,
            'coordination_reference' => null,
            'patient_name' => fake()->name(),
            'items' => [[
                'product_id' => fake()->numberBetween(1, 3000),
                'name' => 'ACETAMINOFEN 500MG 10COMP',
                'sku' => 'MED-'.fake()->numerify('####'),
                'quantity' => $quantity,
            ]],
            'items_count' => 1,
            'units_count' => $quantity,
            'status' => FarmadocOrder::STATUS_PENDING,
            'created_by_user_id' => null,
            'created_by_name' => fake()->name(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => FarmadocOrder::STATUS_COMPLETED]);
    }
}
