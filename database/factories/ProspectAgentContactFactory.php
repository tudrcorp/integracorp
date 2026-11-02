<?php

namespace Database\Factories;

use App\Models\ProspectAgent;
use App\Models\ProspectAgentContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectAgentContact>
 */
class ProspectAgentContactFactory extends Factory
{
    protected $model = ProspectAgentContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prospect_agent_id' => ProspectAgent::query()->value('id') ?? 1,
            'department' => fake()->optional()->randomElement(['Comercial', 'Administración', 'Recursos Humanos', 'Gerencia']),
            'name' => fake()->name(),
            'position' => fake()->optional()->jobTitle(),
            'phone' => fake()->optional()->numerify('04#########'),
            'local_phone' => fake()->optional()->numerify('0212#######'),
            'extensions' => fake()->optional()->numerify('###'),
            'email' => fake()->optional()->safeEmail(),
            'sort_order' => 0,
        ];
    }
}
