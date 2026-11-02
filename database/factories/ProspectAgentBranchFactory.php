<?php

namespace Database\Factories;

use App\Models\ProspectAgent;
use App\Models\ProspectAgentBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectAgentBranch>
 */
class ProspectAgentBranchFactory extends Factory
{
    protected $model = ProspectAgentBranch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prospect_agent_id' => ProspectAgent::query()->value('id') ?? 1,
            'state_id' => null,
            'city_id' => null,
            'name' => 'Sucursal '.fake()->city(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->numerify('04#########'),
            'local_phone' => fake()->optional()->numerify('0212#######'),
            'address' => fake()->optional()->streetAddress(),
            'sort_order' => 0,
        ];
    }
}
