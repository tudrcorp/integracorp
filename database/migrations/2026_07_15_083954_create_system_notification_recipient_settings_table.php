<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notification_recipient_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('notification_key')->unique();
            $table->json('notification_emails')->nullable();
            $table->json('notification_phones')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        $now = now();

        $associateEmails = [];
        $associatePhones = [];
        $associateUpdatedBy = null;
        $associateUpdatedAt = $now;

        if (Schema::hasTable('company_associate_notification_settings')) {
            $legacy = DB::table('company_associate_notification_settings')->where('id', 1)->first();

            if ($legacy !== null) {
                $associateEmails = self::decodeList($legacy->notification_emails ?? null);
                $associatePhones = self::decodeList($legacy->notification_phones ?? null);
                $associateUpdatedBy = $legacy->updated_by;
                $associateUpdatedAt = $legacy->updated_at ?? $now;
            }
        }

        foreach (SystemNotificationKey::managed() as $key) {
            $emails = $key === SystemNotificationKey::CompanyAssociateRegistration
                ? $associateEmails
                : $key->defaultEmails();
            $phones = $key === SystemNotificationKey::CompanyAssociateRegistration
                ? $associatePhones
                : $key->defaultPhones();
            $updatedBy = $key === SystemNotificationKey::CompanyAssociateRegistration
                ? $associateUpdatedBy
                : null;
            $updatedAt = $key === SystemNotificationKey::CompanyAssociateRegistration
                ? $associateUpdatedAt
                : $now;

            DB::table('system_notification_recipient_settings')->insert([
                'notification_key' => $key->value,
                'notification_emails' => json_encode($emails),
                'notification_phones' => json_encode($phones),
                'updated_by' => $updatedBy,
                'created_at' => $now,
                'updated_at' => $updatedAt,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notification_recipient_settings');
    }

    /**
     * @return list<string>
     */
    private static function decodeList(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
        } elseif (is_array($value)) {
            $decoded = $value;
        } else {
            $decoded = [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $decoded,
        )));
    }
};
