<?php

declare(strict_types=1);

namespace App\Support\Birthday;

use App\Models\BirthdayNotificationExclusion;
use Illuminate\Support\Facades\Schema;

final class BirthdayCelebrationRecipientGate
{
    public static function isExcluded(int $notificationId, string $dataType, int $recipientId): bool
    {
        if (! Schema::hasTable('birthday_notification_exclusions')) {
            return false;
        }

        return BirthdayNotificationExclusion::query()
            ->where('birthday_notification_id', $notificationId)
            ->where('data_type', $dataType)
            ->where('recipient_id', $recipientId)
            ->exists();
    }

    public static function setReceives(int $notificationId, string $dataType, int $recipientId, bool $receives): void
    {
        if (! Schema::hasTable('birthday_notification_exclusions')) {
            return;
        }

        $query = BirthdayNotificationExclusion::query()
            ->where('birthday_notification_id', $notificationId)
            ->where('data_type', $dataType)
            ->where('recipient_id', $recipientId);

        if ($receives) {
            $query->delete();

            return;
        }

        BirthdayNotificationExclusion::query()->firstOrCreate([
            'birthday_notification_id' => $notificationId,
            'data_type' => $dataType,
            'recipient_id' => $recipientId,
        ]);
    }

    /**
     * @return array<int, true>
     */
    public static function excludedIdMap(int $notificationId, string $dataType): array
    {
        if ($dataType === '' || ! Schema::hasTable('birthday_notification_exclusions')) {
            return [];
        }

        $ids = BirthdayNotificationExclusion::query()
            ->where('birthday_notification_id', $notificationId)
            ->where('data_type', $dataType)
            ->pluck('recipient_id');

        $map = [];

        foreach ($ids as $id) {
            $map[(int) $id] = true;
        }

        return $map;
    }
}
