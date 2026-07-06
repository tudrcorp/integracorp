<?php

declare(strict_types=1);

namespace App\Support\Companies;

use Illuminate\Support\Facades\Cache;

final class CompanyAssociateDocumentsBellAlert
{
    public static function markPending(int $userId): void
    {
        Cache::put(self::cacheKey($userId), now()->timestamp, now()->addMinutes(10));
    }

    public static function consume(int $userId): bool
    {
        $cacheKey = self::cacheKey($userId);

        if (! Cache::has($cacheKey)) {
            return false;
        }

        Cache::forget($cacheKey);

        return true;
    }

    private static function cacheKey(int $userId): string
    {
        return 'business.database_notification_bell_alert.'.$userId;
    }
}
