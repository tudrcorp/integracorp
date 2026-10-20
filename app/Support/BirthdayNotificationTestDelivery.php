<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\MassNotificationDeliveryStatus;
use App\Http\Controllers\NotificationController;
use App\Mail\NotificationMasiveMailBirthday;
use App\Models\BirthdayNotification;
use App\Support\Birthday\BirthdayCelebrationAccessIssuer;
use App\Support\Birthday\BirthdayCelebrationCard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class BirthdayNotificationTestDelivery
{
    /**
     * @param  array{phone?: string, name?: string|null}  $data
     */
    public static function sendWhatsApp(BirthdayNotification $record, array $data): bool
    {
        $name = self::resolveName($data['name'] ?? null);
        $phone = trim((string) ($data['phone'] ?? ''));

        if ($phone === '') {
            return false;
        }

        [$file, $type] = self::resolveMedia($record);
        $content = trim((string) ($record->content ?? ''));

        if ($record->sends_celebration_card) {
            $animationUrl = self::issueAnimationUrl($record, $name, $phone, null);
            $content .= BirthdayNotificationRunReport::formatCelebrationLinkForMessage($animationUrl);
        }

        try {
            $result = NotificationController::notificationBirthday(
                $name,
                $phone,
                $content,
                $file,
                $type,
            );

            $success = is_array($result) && ($result['status'] ?? null) === 'success';

            BirthdayNotificationRecipientDelivery::recordWhatsappOutcome(
                $record->id,
                $name,
                $phone,
                $success ? MassNotificationDeliveryStatus::Sent : MassNotificationDeliveryStatus::Failed,
                $success ? null : (string) ($result['message'] ?? 'No se pudo enviar la prueba de WhatsApp'),
            );

            return $success;
        } catch (Throwable $exception) {
            Log::error('Prueba WhatsApp de cumpleaños fallida.', [
                'birthday_notification_id' => $record->id,
                'phone' => $phone,
                'message' => $exception->getMessage(),
            ]);

            BirthdayNotificationRecipientDelivery::recordWhatsappOutcome(
                $record->id,
                $name,
                $phone,
                MassNotificationDeliveryStatus::Failed,
                $exception->getMessage(),
            );

            return false;
        }
    }

    /**
     * @param  array{email?: string, name?: string|null}  $data
     */
    public static function sendEmail(BirthdayNotification $record, array $data): bool
    {
        $name = self::resolveName($data['name'] ?? null);
        $email = trim((string) ($data['email'] ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            BirthdayNotificationRecipientDelivery::recordEmailOutcome(
                $record->id,
                $name,
                $email !== '' ? $email : null,
                MassNotificationDeliveryStatus::Skipped,
                'Email mal escrito o inválido',
            );

            return false;
        }

        [$file] = self::resolveMedia($record);
        $animationUrl = $record->sends_celebration_card
            ? self::issueAnimationUrl($record, $name, null, $email)
            : null;

        try {
            Mail::to($email)->sendNow(new NotificationMasiveMailBirthday(
                $name,
                $file,
                $email,
                $animationUrl,
            ));

            BirthdayNotificationRecipientDelivery::recordEmailOutcome(
                $record->id,
                $name,
                $email,
                MassNotificationDeliveryStatus::Sent,
            );

            return true;
        } catch (Throwable $exception) {
            Log::error('Prueba email de cumpleaños fallida.', [
                'birthday_notification_id' => $record->id,
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);

            BirthdayNotificationRecipientDelivery::recordEmailOutcome(
                $record->id,
                $name,
                $email,
                MassNotificationDeliveryStatus::Failed,
                $exception->getMessage(),
            );

            return false;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function resolveMedia(BirthdayNotification $record): array
    {
        if ($record->sends_celebration_card) {
            return [BirthdayCelebrationCard::PUBLIC_RELATIVE_PATH, 'image'];
        }

        $type = (string) ($record->type ?: 'image');
        $file = (string) ($record->file ?? '');

        if ($type === 'url' || $file === '') {
            return [WhatsAppBrandImage::RELATIVE_PATH, 'image'];
        }

        return [$file, $type];
    }

    private static function resolveName(mixed $name): string
    {
        $trimmed = trim((string) ($name ?? ''));

        return $trimmed !== '' ? $trimmed : 'Prueba';
    }

    private static function issueAnimationUrl(
        BirthdayNotification $record,
        string $name,
        ?string $phone,
        ?string $email,
    ): ?string {
        if (! Schema::hasTable('birthday_celebration_accesses')) {
            return null;
        }

        try {
            $delivery = BirthdayNotificationRecipientDelivery::resolveDelivery(
                $record->id,
                $name,
                $email,
                $phone,
            );

            $access = BirthdayCelebrationAccessIssuer::ensureForDelivery(
                $delivery->id,
                $name,
                $phone,
            );

            return route('birthday.animation.access', ['token' => $access->public_token]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo emitir acceso de animación para prueba de cumpleaños.', [
                'birthday_notification_id' => $record->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
