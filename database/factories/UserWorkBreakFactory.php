<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserWorkBreak;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserWorkBreak>
 */
class UserWorkBreakFactory extends Factory
{
    protected $model = UserWorkBreak::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->setTime(12, 0);

        return [
            'user_id' => 1,
            'work_date' => $start->toDateString(),
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes(60),
            'end_reason' => UserWorkBreak::END_MANUAL,
            'started_from' => 'Negocios',
        ];
    }
}
