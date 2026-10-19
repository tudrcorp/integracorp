<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AffiliationCorporateContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliationCorporateContact>
 */
class AffiliationCorporateContactFactory extends Factory
{
    protected $model = AffiliationCorporateContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affiliation_corporate_id' => 1,
            'name' => fake()->name(),
            'relationship' => fake()->optional()->randomElement(['RRHH', 'ADMINISTRACION', 'GERENCIA']),
            'phone' => fake()->optional()->numerify('+58414#######'),
            'email' => fake()->optional()->safeEmail(),
            'sort_order' => 0,
        ];
    }
}
