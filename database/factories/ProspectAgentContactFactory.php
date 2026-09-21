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
            'name' => fake()->name(),
            'position' => fake()->optional()->jobTitle(),
            'phone' => fake()->optional()->numerify('04#########'),
            'email' => fake()->optional()->safeEmail(),
            'sort_order' => 0,
        ];
    }
}
