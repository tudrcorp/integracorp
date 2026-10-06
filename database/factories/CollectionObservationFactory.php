<?php

namespace Database\Factories;

use App\Models\CollectionObservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CollectionObservation>
 */
class CollectionObservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliation_code' => 'TDEC-'.fake()->unique()->numerify('#####'),
            'affiliation_type' => CollectionObservation::TYPE_INDIVIDUAL,
            'collection_id' => null,
            'due_date' => fake()->dateTimeBetween('-30 days', '+60 days')->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, 10, 500),
            'observation' => fake()->sentence(12),
            'created_by' => null,
            'created_by_name' => fake()->name(),
        ];
    }

    public function corporate(): static
    {
        return $this->state(fn (): array => [
            'affiliation_type' => CollectionObservation::TYPE_CORPORATE,
        ]);
    }
}
