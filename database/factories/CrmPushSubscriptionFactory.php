<?php

namespace Database\Factories;

use App\Models\CrmPushSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmPushSubscription>
 */
class CrmPushSubscriptionFactory extends Factory
{
    protected $model = CrmPushSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->numberBetween(1, 50),
            'endpoint' => 'https://push.example.test/'.fake()->uuid(),
            'public_key' => 'p256dh-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aes128gcm',
        ];
    }
}
