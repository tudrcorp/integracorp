<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AffiliationCorporateObservation>
 */
class AffiliationCorporateObservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => $this->faker->paragraph(),
            'created_by' => (string) $this->faker->numberBetween(1, 100),
        ];
    }
}
