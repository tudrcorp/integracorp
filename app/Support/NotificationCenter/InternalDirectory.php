<?php

declare(strict_types=1);

namespace App\Support\NotificationCenter;

use App\Models\SystemNotificationRecipientSetting;
use App\Models\TelegramRecipient;
use App\Models\User;
use App\Support\InternalNotifications\InternalContactResolver;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Directorio del personal de TDG para decidir si un mensaje es interno o externo:
 * usuarios activos @tudrencasa.com, teléfonos del Centro de notificaciones y personas
 * vinculadas a Telegram. Cacheado 10 minutos.
 */
final class InternalDirectory
{
    public const INTERNAL_EMAIL_DOMAIN = '@tudrencasa.com';

    private const CACHE_KEY = 'notification-center:internal-directory:v1';

    /** @var array{phones: array<string, string>}|null */
    private static ?array $directory = null;

    private static int $loadedAt = 0;

    public static function isInternalEmail(string $email): bool
    {
        return str_ends_with(mb_strtolower(trim($email)), self::INTERNAL_EMAIL_DOMAIN);
    }

    /**
     * Nombre del colaborador dueño del teléfono, o null si no es del personal.
     */
    public static function internalNameForPhone(string $phone): ?string
    {
        $key = InternalContactResolver::phoneKey($phone);

        if ($key === '') {
            return null;
        }

        return self::directory()['phones'][$key] ?? null;
    }

    public static function isInternalPhone(string $phone): bool
    {
        return self::internalNameForPhone($phone) !== null;
    }

    public static function flush(): void
    {
        self::$directory = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
        }
    }

    /**
     * @return array{phones: array<string, string>}
     */
    private static function directory(): array
    {
        if (self::$directory !== null && (time() - self::$loadedAt) < 60) {
            return self::$directory;
        }

        try {
            self::$directory = Cache::remember(self::CACHE_KEY, 600, fn (): array => self::build());
        } catch (Throwable) {
            self::$directory = ['phones' => []];
        }

        self::$loadedAt = time();

        return self::$directory;
    }

    /**
     * @return array{phones: array<string, string>}
     */
    private static function build(): array
    {
        $phones = [];

        $add = static function (mixed $phone, string $name) use (&$phones): void {
            $key = InternalContactResolver::phoneKey((string) $phone);

            if ($key !== '' && strlen($key) >= 10) {
                $phones[$key] ??= $name;
            }
        };

        User::query()
            ->where('email', 'like', '%'.self::INTERNAL_EMAIL_DOMAIN)
            ->whereNotNull('phone')
            ->get(['name', 'phone'])
            ->each(fn (User $user) => $add($user->phone, (string) $user->name));

        TelegramRecipient::query()
            ->whereNotNull('phone')
            ->whereIn('type', ['user'])
            ->get(['name', 'phone'])
            ->each(fn (TelegramRecipient $recipient) => $add($recipient->phone, (string) $recipient->name));

        SystemNotificationRecipientSetting::query()
            ->get(['notification_phones'])
            ->each(function (SystemNotificationRecipientSetting $setting) use ($add): void {
                foreach ($setting->phones() as $phone) {
                    $add($phone, 'Personal TDG');
                }
            });

        return ['phones' => $phones];
    }
}
