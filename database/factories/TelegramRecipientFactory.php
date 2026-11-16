<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TelegramRecipientStatus;
use App\Enums\TelegramRecipientType;
use App\Models\TelegramRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramRecipient>
 */
class TelegramRecipientFactory extends Factory
{
    protected $model = TelegramRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => TelegramRecipientType::External,
            'status' => TelegramRecipientStatus::Active,
            'name' => fake()->name(),
            'chat_id' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0414'.fake()->numerify('#######'),
            'reason' => 'Contacto de prueba',
            'linked_at' => now(),
            'created_by' => 'factory',
        ];
    }

    public function group(): static
    {
        return $this->state(fn (): array => [
            'type' => TelegramRecipientType::Group,
            'name' => 'Grupo '.fake()->word(),
            'chat_id' => '-100'.fake()->unique()->numerify('##########'),
            'email' => null,
            'phone' => null,
            'reason' => null,
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (): array => ['status' => TelegramRecipientStatus::PendingApproval]);
    }

    public function pendingLink(): static
    {
        return $this->state(fn (): array => ['status' => TelegramRecipientStatus::PendingLink, 'chat_id' => null, 'linked_at' => null]);
    }
}
