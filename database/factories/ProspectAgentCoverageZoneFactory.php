<?php

namespace Database\Factories;

use App\Models\ProspectAgent;
use App\Models\ProspectAgentCoverageZone;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectAgentCoverageZone>
 */
class ProspectAgentCoverageZoneFactory extends Factory
{
    protected $model = ProspectAgentCoverageZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prospect_agent_id' => ProspectAgent::query()->value('id') ?? 1,
            'classification' => fake()->optional()->randomElement(['Corretaje', 'Viajes', 'Corporativo']),
            'service_type' => fake()->optional()->randomElement(['Venta de planes', 'Asistencia de viaje', 'Renovaciones']),
            'state_id' => State::query()->value('id') ?? 1,
            'city_id' => null,
            'sort_order' => 0,
        ];
    }
}
