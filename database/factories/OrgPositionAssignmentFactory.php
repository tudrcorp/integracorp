<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrgAssignmentRole;
use App\Models\OrgPositionAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgPositionAssignment>
 */
class OrgPositionAssignmentFactory extends Factory
{
    protected $model = OrgPositionAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role' => OrgAssignmentRole::Holder,
            'starts_on' => now()->toDateString(),
        ];
    }
}
