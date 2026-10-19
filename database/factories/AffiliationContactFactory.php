<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AffiliationContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliationContact>
 */
class AffiliationContactFactory extends Factory
{
    protected $model = AffiliationContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliation_id' => 1,
            'name' => fake()->name(),
            'relationship' => fake()->optional()->randomElement(['ESPOSA', 'ESPOSO', 'HIJO', 'HIJA', 'TRABAJO']),
            'phone' => fake()->optional()->numerify('+58412#######'),
            'email' => fake()->optional()->safeEmail(),
            'sort_order' => 0,
        ];
    }
}
