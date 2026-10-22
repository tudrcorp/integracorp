<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrgPositionKind;
use App\Models\OrgPosition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrgPosition>
 */
class OrgPositionFactory extends Factory
{
    protected $model = OrgPosition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position_key' => (string) Str::uuid(),
            'title' => 'Gerencia de '.fake()->word(),
            'kind' => OrgPositionKind::Line,
            'seats' => 1,
            'sort_order' => 0,
        ];
    }
}
