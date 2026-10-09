<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserActivityDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserActivityDay>
 */
class UserActivityDayFactory extends Factory
{
    protected $model = UserActivityDay::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $active = fake()->numberBetween(60, 360);
        $idle = fake()->numberBetween(0, 120);
        $background = fake()->numberBetween(0, 60);

        return [
            'user_id' => 1,
            'activity_date' => now()->toDateString(),
            'first_minute' => 8 * 60,
            'last_minute' => 8 * 60 + $active + $idle + $background,
            'online_minutes' => $active + $idle + $background,
            'active_minutes' => $active,
            'idle_minutes' => $idle,
            'background_minutes' => $background,
            'page_views' => fake()->numberBetween(5, 80),
            'actions' => fake()->numberBetween(10, 300),
            'downloads' => fake()->numberBetween(0, 10),
            'hourly_active' => array_fill(0, 24, 0),
            'minute_states' => null,
        ];
    }
}
