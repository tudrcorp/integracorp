<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DownloadZoneLike;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownloadZoneLike>
 */
class DownloadZoneLikeFactory extends Factory
{
    protected $model = DownloadZoneLike::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'download_zone_id' => fake()->numberBetween(1, 9999),
        ];
    }
}
