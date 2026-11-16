<?php

declare(strict_types=1);

namespace App\Support\InternalNotifications;

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\SystemNotificationKey;
use App\Enums\TelegramDeliveryMode;
use App\Enums\TelegramRecipientStatus;
use App\Jobs\SendTelegramDeliveryJob;
use App\Models\NotificationDelivery;
use App\Models\TelegramRecipient;
use App\Support\SystemNotificationRecipients;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pieza de migración de los avisos internos existentes a Telegram.
 *
 * Cada aviso sigue armando su mensaje y sus correos como siempre; antes de su
 * bucle «un WhatsApp por teléfono» llama a route(), que:
 *
 * - envía por Telegram a los teléfonos (y correos) de personas vinculadas y a
 *   los grupos elegidos en el Centro de notificaciones, y
 * - devuelve los teléfonos que deben seguir saliendo por WhatsApp, que el aviso
 *   envía con su mismo código de siempre (imagen, documento, síncrono o en cola).
 *
 * Con Telegram apagado, sin token, o con el aviso en «Apagado», devuelve todos
 * los teléfonos: el aviso se comporta exactamente como antes.
 */
final class InternalMessaging
{
    /**
     * @param  list<string>  $phones  teléfonos ya validados por el aviso
     * @param  list<string>  $emails  correos del aviso: solo para reconocer a personas vinculadas (no se les envía correo desde aquí)
     * @param  bool  $immediate  sin pasar por la cola (avisos que deben llegar aunque los workers estén caídos)
     * @param  bool  $bypassTelegram  forzar WhatsApp (por ejemplo, alertas sobre el propio Telegram)
     * @param  InternalAttachment|null  $attachment  documento que se sube a Telegram junto al mensaje
     * @param  string|null  $telegramMessage  versión para Telegram cuando debe llevar menos datos que el
     *                                        WhatsApp (por ejemplo, sin datos clínicos); también es la que sale en su respaldo
     * @return array{whatsapp: list<string>, telegram: int, groups: int, delivered: int, mode: TelegramDeliveryMode}
     *
     * «delivered»: en los avisos inmediatos, cuántos llegaron en el acto (por Telegram o por su WhatsApp de respaldo).
     */
    public static function route(
        SystemNotificationKey $key,
        string $title,
        string $whatsAppMessage,
        array $phones,
        array $emails = [],
        bool $immediate = false,
        bool $critical = false,
        bool $bypassTelegram = false,
        ?string $actor = null,
        ?InternalAttachment $attachment = null,
        ?string $telegramMessage = null,
    ): array {
        $phones = array_values(array_unique(array_filter(array_map(static fn (mixed $phone): string => trim((string) $phone), $phones))));
        $untouched = ['whatsapp' => $phones, 'telegram' => 0, 'groups' => 0, 'delivered' => 0, 'mode' => TelegramDeliveryMode::Off];

        try {
            $mode = self::mode($key, $immediate, $bypassTelegram);

            if ($mode === TelegramDeliveryMode::Off) {
                return $untouched;
            }

            $notification = new InternalNotification($key, $title, $telegramMessage ?? $whatsAppMessage, $critical, $actor, false, true, $attachment);
            [$recipients, $remainingPhones] = self::matchPeople($phones, $emails);

            /** Más las personas elegidas a mano en el Centro de notificaciones. */
            $byId = [];

            foreach ([...$recipients, ...InternalContactResolver::chosenPeople(SystemNotificationRecipients::setting($key))] as $recipient) {
                $byId[$recipient->getKey()] = $recipient;
            }

            $recipients = array_values($byId);
            $groups = $mode->includesGroups() ? self::activeGroups($key) : [];

            if ($mode === TelegramDeliveryMode::Group && $groups === []) {
                return $untouched;
            }

            $ids = DB::transaction(function () use ($notification, $mode, $recipients, $groups): array {
                $ids = [];

                if ($mode->includesPeople()) {
                    foreach ($recipients as $recipient) {
                        $ids[] = InternalNotificationDispatcher::record($notification, NotificationDeliveryChannel::Telegram, NotificationDeliveryStatus::Pending, $recipient->name, $recipient)->getKey();
                    }
                }

                foreach ($groups as $group) {
                    $ids[] = InternalNotificationDispatcher::record($notification, NotificationDeliveryChannel::Telegram, NotificationDeliveryStatus::Pending, $group->name, $group)->getKey();
                }

                return $ids;
            });
        } catch (Throwable $exception) {
            Log::error('InternalMessaging: no se pudo preparar el envío por Telegram; sale por WhatsApp como siempre.', [
                'key' => $key->value,
                'error' => $exception->getMessage(),
            ]);

            return $untouched;
        }

        foreach ($ids as $id) {
            if ($immediate) {
                SendTelegramDeliveryJob::dispatchSync((int) $id, true);
            } else {
                InternalNotificationDispatcher::queueTelegram((int) $id);
            }
        }

        return [
            'whatsapp' => $mode === TelegramDeliveryMode::Group ? [] : $remainingPhones,
            'telegram' => $mode->includesPeople() ? count($recipients) : 0,
            'groups' => count($groups),
            'delivered' => $immediate ? self::deliveredNow($ids) : 0,
            'mode' => $mode,
        ];
    }

    /**
     * Aviso para una persona concreta (asignado, invitado, creador). Si tiene Telegram
     * vinculado y el aviso está en «Persona», sale por Telegram y devuelve true: quien
     * llama ya no envía el WhatsApp. Si devuelve false, el aviso sigue como siempre.
     *
     * La persona se reconoce por su usuario de IntegraCorp y, si no, por su teléfono o su correo.
     */
    public static function routeToPerson(
        SystemNotificationKey $key,
        string $title,
        string $whatsAppMessage,
        ?int $userId,
        ?string $phone = null,
        ?string $actor = null,
        ?string $telegramMessage = null,
        ?string $email = null,
    ): bool {
        try {
            if (! self::mode($key)->includesPeople()) {
                return false;
            }

            $people = InternalContactResolver::linkedPeople();
            $recipient = $userId !== null
                ? $people->first(fn (TelegramRecipient $person): bool => (int) $person->user_id === $userId)
                : null;

            if ($recipient === null && filled($phone)) {
                $phoneKey = InternalContactResolver::phoneKey((string) $phone);
                $recipient = $phoneKey === '' ? null : $people->first(function (TelegramRecipient $person) use ($phoneKey): bool {
                    foreach (array_filter([$person->phone, $person->user?->phone]) as $candidate) {
                        if (InternalContactResolver::phoneKey((string) $candidate) === $phoneKey) {
                            return true;
                        }
                    }

                    return false;
                });
            }

            if ($recipient === null && filled($email)) {
                $email = mb_strtolower(trim((string) $email));
                $recipient = $people->first(fn (TelegramRecipient $person): bool => in_array($email, array_map(
                    static fn (mixed $candidate): string => mb_strtolower(trim((string) $candidate)),
                    array_filter([$person->email, $person->user?->email]),
                ), true));
            }

            if (! $recipient instanceof TelegramRecipient) {
                return false;
            }

            $notification = new InternalNotification($key, $title, $telegramMessage ?? $whatsAppMessage, false, $actor, false, true);
            $delivery = DB::transaction(fn () => InternalNotificationDispatcher::record($notification, NotificationDeliveryChannel::Telegram, NotificationDeliveryStatus::Pending, $recipient->name, $recipient));
        } catch (Throwable $exception) {
            Log::error('InternalMessaging: no se pudo preparar el aviso personal por Telegram; sale por WhatsApp como siempre.', [
                'key' => $key->value,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        InternalNotificationDispatcher::queueTelegram((int) $delivery->getKey());

        return true;
    }

    /**
     * Destino efectivo. Un aviso inmediato no usa Telegram si el último chequeo
     * dijo que no responde: esperar su tiempo de espera retrasaría la alerta.
     */
    public static function mode(SystemNotificationKey $key, bool $immediate = false, bool $bypassTelegram = false): TelegramDeliveryMode
    {
        if ($bypassTelegram || ! $key->supportsTelegram() || ! InternalNotificationDispatcher::telegramAvailable()) {
            return TelegramDeliveryMode::Off;
        }

        $setting = SystemNotificationRecipients::setting($key);

        if (! $setting->isActive()) {
            return TelegramDeliveryMode::Off;
        }

        if ($immediate && self::telegramKnownDown()) {
            return TelegramDeliveryMode::Off;
        }

        $mode = $setting->telegramMode();

        if ($mode->includesGroups() && ! $key->allowsTelegramGroups()) {
            return TelegramDeliveryMode::Person;
        }

        return $mode;
    }

    /**
     * @param  list<string>  $phones
     * @param  list<string>  $emails
     * @return array{0: list<TelegramRecipient>, 1: list<string>}
     */
    private static function matchPeople(array $phones, array $emails): array
    {
        $people = InternalContactResolver::linkedPeople();
        $byPhone = [];
        $byEmail = [];

        foreach ($people as $recipient) {
            foreach (array_filter([$recipient->phone, $recipient->user?->phone]) as $phone) {
                $phoneKey = InternalContactResolver::phoneKey((string) $phone);

                if ($phoneKey !== '') {
                    $byPhone[$phoneKey] = $recipient;
                }
            }

            foreach (array_filter([$recipient->email, $recipient->user?->email]) as $email) {
                $byEmail[mb_strtolower(trim((string) $email))] = $recipient;
            }
        }

        $matched = [];
        $remaining = [];

        foreach ($phones as $phone) {
            $phoneKey = InternalContactResolver::phoneKey($phone);

            if ($phoneKey !== '' && isset($byPhone[$phoneKey])) {
                $matched[$byPhone[$phoneKey]->getKey()] = $byPhone[$phoneKey];
            } else {
                $remaining[] = $phone;
            }
        }

        foreach ($emails as $email) {
            $email = mb_strtolower(trim((string) $email));

            if (isset($byEmail[$email])) {
                $matched[$byEmail[$email]->getKey()] = $byEmail[$email];
            }
        }

        return [array_values($matched), $remaining];
    }

    /**
     * @return list<TelegramRecipient>
     */
    private static function activeGroups(SystemNotificationKey $key): array
    {
        $ids = SystemNotificationRecipients::setting($key)->telegramGroupIds();

        if ($ids === []) {
            return [];
        }

        return TelegramRecipient::query()
            ->groups()
            ->whereKey($ids)
            ->where('status', TelegramRecipientStatus::Active->value)
            ->whereNotNull('chat_id')
            ->get()
            ->all();
    }

    /**
     * Entregas resueltas en el acto: por Telegram o, si falló, por su respaldo.
     *
     * @param  list<int|string>  $ids
     */
    private static function deliveredNow(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return NotificationDelivery::query()
            ->whereKey($ids)
            ->where(function ($query): void {
                $query->where('status', NotificationDeliveryStatus::Sent->value)
                    ->orWhere(function ($fellBack): void {
                        $fellBack->where('status', NotificationDeliveryStatus::FellBack->value)
                            ->whereHas('fallbacks', fn ($child) => $child->where('status', NotificationDeliveryStatus::Sent->value));
                    });
            })
            ->count();
    }

    private static function telegramKnownDown(): bool
    {
        try {
            $cached = Cache::get('telegram:health');

            return is_array($cached) && ($cached['configured'] ?? false) && ! ($cached['ok'] ?? true);
        } catch (Throwable) {
            return false;
        }
    }
}
