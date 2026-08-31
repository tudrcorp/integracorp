<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_notification_recipient_settings')) {
            return;
        }

        $key = SystemNotificationKey::TelemedicineServiceLimitOverride;

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
            'is_active' => true,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_notification_recipient_settings')) {
            return;
        }

        DB::table('system_notification_recipient_settings')
            ->where('notification_key', SystemNotificationKey::TelemedicineServiceLimitOverride->value)
            ->delete();
    }
};
