<?php

namespace App\Support;

use App\Enums\MassNotificationDeliveryStatus;
use App\Models\DataNotification;
use App\Models\MassNotification;
use Illuminate\Support\Facades\DB;

class MassNotificationRecipientDelivery
{
    public static function markEmailPending(int $dataNotificationId): void
    {
        self::updateEmail($dataNotificationId, MassNotificationDeliveryStatus::Pending);
    }

    public static function markEmailSent(int $dataNotificationId): void
    {
        self::updateEmail($dataNotificationId, MassNotificationDeliveryStatus::Sent);
    }

    public static function markEmailFailed(int $dataNotificationId, string $errorMessage): void
    {
        self::updateEmail($dataNotificationId, MassNotificationDeliveryStatus::Failed, $errorMessage);
    }

    public static function markEmailSkipped(int $dataNotificationId, string $reason): void
    {
        self::updateEmail($dataNotificationId, MassNotificationDeliveryStatus::Skipped, $reason);
    }

    public static function markWhatsappPending(int $dataNotificationId): void
    {
        self::updateWhatsapp($dataNotificationId, MassNotificationDeliveryStatus::Pending);
    }

    public static function markWhatsappSent(int $dataNotificationId): void
    {
        self::updateWhatsapp($dataNotificationId, MassNotificationDeliveryStatus::Sent);
    }

    public static function markWhatsappFailed(int $dataNotificationId, string $errorMessage): void
    {
        self::updateWhatsapp($dataNotificationId, MassNotificationDeliveryStatus::Failed, $errorMessage);
    }

    public static function markWhatsappSkipped(int $dataNotificationId, string $reason): void
    {
        self::updateWhatsapp($dataNotificationId, MassNotificationDeliveryStatus::Skipped, $reason);
    }

    public static function recordTestEmail(MassNotification $record, string $email, bool $success, ?string $errorMessage = null): void
    {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($success) {
            $record->increment('test_email_success_count');
        } else {
            $record->increment('test_email_failed_count');
        }

        $record->forceFill([
            'last_test_email_to' => $email,
            'last_test_email_at' => now(),
            'last_test_email_error' => $success ? null : mb_substr($errorMessage ?? 'Error desconocido', 0, 1000),
        ])->save();

        $recipient = DataNotification::query()
            ->where('mass_notification_id', $record->id)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->first();

        if ($recipient === null) {
            return;
        }

        if ($success) {
            self::markEmailSent($recipient->id);

            return;
        }

        self::markEmailFailed($recipient->id, $errorMessage ?? 'Error en prueba de correo');
    }

    public static function recordTestWhatsapp(MassNotification $record, string $phone, bool $success, ?string $errorMessage = null): void
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? trim($phone);

        if ($success) {
            $record->increment('test_whatsapp_success_count');
        } else {
            $record->increment('test_whatsapp_failed_count');
        }

        $record->forceFill([
            'last_test_whatsapp_to' => $phone,
            'last_test_whatsapp_at' => now(),
            'last_test_whatsapp_error' => $success ? null : mb_substr($errorMessage ?? 'Error desconocido', 0, 1000),
        ])->save();

        $recipient = DataNotification::query()
            ->where('mass_notification_id', $record->id)
            ->get()
            ->first(fn (DataNotification $row): bool => preg_replace('/\D+/', '', (string) $row->phone) === $normalizedPhone);

        if ($recipient === null) {
            return;
        }

        if ($success) {
            self::markWhatsappSent($recipient->id);

            return;
        }

        self::markWhatsappFailed($recipient->id, $errorMessage ?? 'Error en prueba de WhatsApp');
    }

    /**
     * @return array{
     *     email: array{sent: int, failed: int, pending: int, skipped: int},
     *     whatsapp: array{sent: int, failed: int, pending: int, skipped: int}
     * }
     */
    public static function summarizeForNotification(int $massNotificationId): array
    {
        $rows = DataNotification::query()
            ->where('mass_notification_id', $massNotificationId)
            ->get(['email_status', 'whatsapp_status']);

        return [
            'email' => self::summarizeChannel($rows->pluck('email_status')),
            'whatsapp' => self::summarizeChannel($rows->pluck('whatsapp_status')),
        ];
    }

    /**
     * @param  iterable<mixed>  $statuses
     * @return array{sent: int, failed: int, pending: int, skipped: int}
     */
    private static function summarizeChannel(iterable $statuses): array
    {
        $summary = [
            'sent' => 0,
            'failed' => 0,
            'pending' => 0,
            'skipped' => 0,
        ];

        foreach ($statuses as $status) {
            if ($status === null || $status === '') {
                continue;
            }

            $value = $status instanceof MassNotificationDeliveryStatus
                ? $status->value
                : (string) $status;

            if (array_key_exists($value, $summary)) {
                $summary[$value]++;
            }
        }

        return $summary;
    }

    private static function updateEmail(
        int $dataNotificationId,
        MassNotificationDeliveryStatus $status,
        ?string $errorMessage = null,
    ): void {
        DB::table('data_notifications')
            ->where('id', $dataNotificationId)
            ->update([
                'email_status' => $status->value,
                'email_sent_at' => $status === MassNotificationDeliveryStatus::Sent ? now() : null,
                'email_error' => in_array($status, [MassNotificationDeliveryStatus::Failed, MassNotificationDeliveryStatus::Skipped], true)
                    ? mb_substr($errorMessage ?? 'Sin detalle', 0, 1000)
                    : null,
                'updated_at' => now(),
            ]);
    }

    private static function updateWhatsapp(
        int $dataNotificationId,
        MassNotificationDeliveryStatus $status,
        ?string $errorMessage = null,
    ): void {
        DB::table('data_notifications')
            ->where('id', $dataNotificationId)
            ->update([
                'whatsapp_status' => $status->value,
                'whatsapp_sent_at' => $status === MassNotificationDeliveryStatus::Sent ? now() : null,
                'whatsapp_error' => in_array($status, [MassNotificationDeliveryStatus::Failed, MassNotificationDeliveryStatus::Skipped], true)
                    ? mb_substr($errorMessage ?? 'Sin detalle', 0, 1000)
                    : null,
                'updated_at' => now(),
            ]);
    }
}
