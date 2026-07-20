<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MassNotificationDeliveryStatus;
use App\Models\BirthdayNotificationDelivery;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BirthdayNotificationRecipientDelivery
{
    public static function markEmailPending(int $deliveryId): void
    {
        self::updateEmail($deliveryId, MassNotificationDeliveryStatus::Pending);
    }

    public static function markEmailSent(int $deliveryId): void
    {
        self::updateEmail($deliveryId, MassNotificationDeliveryStatus::Sent);
    }

    public static function markEmailFailed(int $deliveryId, string $errorMessage): void
    {
        self::updateEmail($deliveryId, MassNotificationDeliveryStatus::Failed, $errorMessage);
    }

    public static function markEmailSkipped(int $deliveryId, string $reason): void
    {
        self::updateEmail($deliveryId, MassNotificationDeliveryStatus::Skipped, $reason);
    }

    public static function markWhatsappPending(int $deliveryId): void
    {
        self::updateWhatsapp($deliveryId, MassNotificationDeliveryStatus::Pending);
    }

    public static function markWhatsappSent(int $deliveryId): void
    {
        self::updateWhatsapp($deliveryId, MassNotificationDeliveryStatus::Sent);
    }

    public static function markWhatsappFailed(int $deliveryId, string $errorMessage): void
    {
        self::updateWhatsapp($deliveryId, MassNotificationDeliveryStatus::Failed, $errorMessage);
    }

    public static function markWhatsappSkipped(int $deliveryId, string $reason): void
    {
        self::updateWhatsapp($deliveryId, MassNotificationDeliveryStatus::Skipped, $reason);
    }

    public static function recordEmailOutcome(
        int $birthdayNotificationId,
        string $fullName,
        ?string $email,
        MassNotificationDeliveryStatus $status,
        ?string $errorMessage = null,
        ?string $phone = null,
        ?CarbonInterface $deliveryDate = null,
    ): BirthdayNotificationDelivery {
        $delivery = self::resolveDelivery(
            $birthdayNotificationId,
            $fullName,
            $email,
            $phone,
            $deliveryDate,
        );

        self::updateEmail($delivery->id, $status, $errorMessage);

        return $delivery->refresh();
    }

    public static function recordWhatsappOutcome(
        int $birthdayNotificationId,
        string $fullName,
        ?string $phone,
        MassNotificationDeliveryStatus $status,
        ?string $errorMessage = null,
        ?string $email = null,
        ?CarbonInterface $deliveryDate = null,
    ): BirthdayNotificationDelivery {
        $delivery = self::resolveDelivery(
            $birthdayNotificationId,
            $fullName,
            $email,
            $phone,
            $deliveryDate,
        );

        self::updateWhatsapp($delivery->id, $status, $errorMessage);

        return $delivery->refresh();
    }

    /**
     * @return array{
     *     email: array{sent: int, failed: int, pending: int, skipped: int},
     *     whatsapp: array{sent: int, failed: int, pending: int, skipped: int}
     * }
     */
    public static function summarizeForNotification(int $birthdayNotificationId, ?CarbonInterface $deliveryDate = null): array
    {
        $query = BirthdayNotificationDelivery::query()
            ->where('birthday_notification_id', $birthdayNotificationId);

        if ($deliveryDate !== null) {
            $query->whereDate('delivery_date', $deliveryDate->toDateString());
        }

        $rows = $query->get(['email_status', 'whatsapp_status']);

        return [
            'email' => self::summarizeChannel($rows->pluck('email_status')),
            'whatsapp' => self::summarizeChannel($rows->pluck('whatsapp_status')),
        ];
    }

    public static function resolveDelivery(
        int $birthdayNotificationId,
        string $fullName,
        ?string $email = null,
        ?string $phone = null,
        ?CarbonInterface $deliveryDate = null,
    ): BirthdayNotificationDelivery {
        $date = ($deliveryDate ?? now())->toDateString();
        $normalizedName = trim($fullName);

        $query = BirthdayNotificationDelivery::query()
            ->where('birthday_notification_id', $birthdayNotificationId)
            ->whereDate('delivery_date', $date)
            ->where('full_name', $normalizedName);

        if (filled($phone)) {
            $normalizedPhone = preg_replace('/\D+/', '', (string) $phone) ?? '';
            $existing = (clone $query)->get()->first(
                fn (BirthdayNotificationDelivery $row): bool => preg_replace('/\D+/', '', (string) $row->phone) === $normalizedPhone
                    || blank($row->phone),
            );

            if ($existing !== null) {
                if (blank($existing->phone)) {
                    $existing->forceFill(['phone' => $phone])->save();
                }

                if (filled($email) && blank($existing->email)) {
                    $existing->forceFill(['email' => $email])->save();
                }

                return $existing->refresh();
            }
        }

        if (filled($email)) {
            $normalizedEmail = mb_strtolower(trim($email));
            $existing = (clone $query)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                ->first();

            if ($existing === null) {
                $existing = (clone $query)->whereNull('email')->first();
            }

            if ($existing !== null) {
                if (blank($existing->email)) {
                    $existing->forceFill(['email' => $email])->save();
                }

                if (filled($phone) && blank($existing->phone)) {
                    $existing->forceFill(['phone' => $phone])->save();
                }

                return $existing->refresh();
            }
        }

        $existingByName = $query->first();

        if ($existingByName !== null) {
            $existingByName->forceFill(array_filter([
                'email' => filled($email) && blank($existingByName->email) ? $email : null,
                'phone' => filled($phone) && blank($existingByName->phone) ? $phone : null,
            ], fn ($value) => $value !== null))->save();

            return $existingByName->refresh();
        }

        return BirthdayNotificationDelivery::query()->create([
            'birthday_notification_id' => $birthdayNotificationId,
            'full_name' => $normalizedName,
            'email' => $email,
            'phone' => $phone,
            'delivery_date' => $date,
        ]);
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
        int $deliveryId,
        MassNotificationDeliveryStatus $status,
        ?string $errorMessage = null,
    ): void {
        DB::table('birthday_notification_deliveries')
            ->where('id', $deliveryId)
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
        int $deliveryId,
        MassNotificationDeliveryStatus $status,
        ?string $errorMessage = null,
    ): void {
        DB::table('birthday_notification_deliveries')
            ->where('id', $deliveryId)
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
