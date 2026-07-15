<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SystemNotificationKey;
use App\Models\SystemNotificationRecipientSetting;

final class SystemNotificationRecipients
{
    /**
     * @return list<string>
     */
    public static function emails(SystemNotificationKey $key): array
    {
        return SystemNotificationRecipientSetting::for($key)->emails();
    }

    /**
     * @return list<string>
     */
    public static function phones(SystemNotificationKey $key): array
    {
        return SystemNotificationRecipientSetting::for($key)->phones();
    }

    public static function setting(SystemNotificationKey $key): SystemNotificationRecipientSetting
    {
        return SystemNotificationRecipientSetting::for($key);
    }

    public static function isActive(SystemNotificationKey $key): bool
    {
        return SystemNotificationRecipientSetting::for($key)->isActive();
    }
}
