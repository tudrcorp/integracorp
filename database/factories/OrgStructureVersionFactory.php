<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrgStructureVersionStatus;
use App\Models\OrgStructureVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgStructureVersion>
 */
class OrgStructureVersionFactory extends Factory
{
    protected $model = OrgStructureVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Organigrama '.fake()->year(),
            'status' => OrgStructureVersionStatus::Draft,
            'lock_version' => 0,
        ];
    }

    public function current(): static
    {
        return $this->state(fn (): array => [
            'status' => OrgStructureVersionStatus::Current,
            'current_marker' => 1,
            'published_at' => now(),
        ]);
    }
}
