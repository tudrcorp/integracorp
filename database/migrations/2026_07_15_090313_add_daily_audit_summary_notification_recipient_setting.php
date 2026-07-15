<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $key = SystemNotificationKey::DailyAuditSummary;

        $exists = DB::table('system_notification_recipient_settings')
            ->where('notification_key', $key->value)
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        DB::table('system_notification_recipient_settings')->insert([
            'notification_key' => $key->value,
            'notification_emails' => json_encode($key->defaultEmails()),
            'notification_phones' => json_encode($key->defaultPhones()),
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('system_notification_recipient_settings')
            ->where('notification_key', SystemNotificationKey::DailyAuditSummary->value)
            ->delete();
    }
};
