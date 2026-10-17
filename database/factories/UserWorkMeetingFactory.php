<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserWorkMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserWorkMeeting>
 */
class UserWorkMeetingFactory extends Factory
{
    protected $model = UserWorkMeeting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->setTime(10, 0);

        return [
            'user_id' => 1,
            'work_date' => $start->toDateString(),
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes(45),
            'end_reason' => UserWorkMeeting::END_MANUAL,
            'reason' => 'Reunión de seguimiento',
            'started_from' => 'business',
        ];
    }
}
