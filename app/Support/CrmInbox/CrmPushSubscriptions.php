<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmPushSubscription;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el navegador donde el analista pidió avisos y lo olvida al salir.
 */
final class CrmPushSubscriptions
{
    public static function store(int $userId, string $endpoint, string $publicKey, string $authToken, string $encoding): void
    {
        CrmPushSubscription::query()->updateOrCreate(
            ['endpoint' => $endpoint],
            [
                'user_id' => $userId,
                'public_key' => $publicKey,
                'auth_token' => $authToken,
                'content_encoding' => $encoding !== '' ? $encoding : 'aes128gcm',
            ],
        );
    }

    public static function forgetForUser(int $userId): void
    {
        if (! Schema::hasTable('crm_push_subscriptions')) {
            return;
        }

        CrmPushSubscription::query()->where('user_id', $userId)->delete();
    }

    public static function onLogout(Logout $event): void
    {
        $id = $event->user?->getAuthIdentifier();

        if (! is_numeric($id)) {
            return;
        }

        self::forgetForUser((int) $id);
    }
}
