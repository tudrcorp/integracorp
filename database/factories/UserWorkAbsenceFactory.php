<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserWorkAbsence;
use App\Support\UserActivity\Workday\WorkAbsenceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserWorkAbsence>
 */
class UserWorkAbsenceFactory extends Factory
{
    protected $model = UserWorkAbsence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 1,
            'type' => WorkAbsenceType::Vacation,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addDays(4)->toDateString(),
            'reason' => null,
        ];
    }
}
