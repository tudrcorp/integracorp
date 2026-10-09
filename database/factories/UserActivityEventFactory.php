<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserActivityEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserActivityEvent>
 */
class UserActivityEventFactory extends Factory
{
    protected $model = UserActivityEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_key' => (string) Str::uuid(),
            'user_id' => 1,
            'occurred_at' => now(),
            'type' => 'page',
            'label' => 'Afiliaciones › Individuales',
            'panel' => 'Negocios',
            'page' => null,
            'path' => '/business/affiliations',
            'duration_ms' => fake()->numberBetween(80, 900),
            'status' => 200,
        ];
    }
}
