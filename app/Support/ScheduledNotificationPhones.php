<?php

declare(strict_types=1);

namespace App\Support;

final class ScheduledNotificationPhones
{
    /** @var list<string> */
    public const DEFAULT_PHONES = [
        '04127018390',
        '04143027250',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $phones = config('scheduled-notifications.phones');

        if (! is_array($phones) || $phones === []) {
            return self::DEFAULT_PHONES;
        }

        $normalized = [];

        foreach ($phones as $phone) {
            $value = trim((string) $phone);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized !== [] ? array_values(array_unique($normalized)) : self::DEFAULT_PHONES;
    }
}
