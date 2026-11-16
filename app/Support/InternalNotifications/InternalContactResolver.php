<?php

declare(strict_types=1);

namespace App\Support\InternalNotifications;

use App\Enums\TelegramRecipientStatus;
use App\Enums\TelegramRecipientType;
use App\Models\SystemNotificationRecipientSetting;
use App\Models\TelegramRecipient;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Cruza los contactos de un aviso del Centro de notificaciones (correos y
 * teléfonos) con las personas vinculadas a Telegram. El Centro sigue siendo la
 * única lista de personas: Telegram es solo otro canal para llegarles.
 */
final class InternalContactResolver
{
    /**
     * @return array{
     *     telegram: Collection<int, TelegramRecipient>,
     *     phones: list<string>,
     *     emails: list<string>,
     *     users: Collection<int, User>
     * }
     */
    public static function resolve(SystemNotificationRecipientSetting $setting, bool $useTelegram): array
    {
        $emails = array_values(array_unique(array_map(static fn (string $email): string => mb_strtolower(trim($email)), $setting->emails())));
        $phones = array_values(array_unique($setting->phones()));

        $users = $emails === []
            ? collect()
            : User::query()->whereIn('email', $emails)->get(['id', 'name', 'email', 'phone', 'status']);

        if (! $useTelegram) {
            return ['telegram' => collect(), 'phones' => $phones, 'emails' => $emails, 'users' => $users];
        }

        $people = self::linkedPeople();

        $byEmail = [];
        $byPhone = [];

        foreach ($people as $recipient) {
            foreach (array_filter([$recipient->email, $recipient->user?->email]) as $email) {
                $byEmail[mb_strtolower(trim((string) $email))] = $recipient;
            }

            foreach (array_filter([$recipient->phone, $recipient->user?->phone]) as $phone) {
                $key = self::phoneKey((string) $phone);

                if ($key !== '') {
                    $byPhone[$key] = $recipient;
                }
            }
        }

        $telegram = [];
        $uncoveredEmails = [];
        $uncoveredPhones = [];

        /** Telegram reemplaza al WhatsApp, no al correo: todos los correos del aviso se siguen enviando. */
        foreach ($emails as $email) {
            if (isset($byEmail[$email])) {
                $telegram[$byEmail[$email]->getKey()] = $byEmail[$email];
            }

            $uncoveredEmails[] = $email;
        }

        foreach ($phones as $phone) {
            $key = self::phoneKey($phone);

            if ($key !== '' && isset($byPhone[$key])) {
                $telegram[$byPhone[$key]->getKey()] = $byPhone[$key];
            } else {
                $uncoveredPhones[] = $phone;
            }
        }

        foreach (self::chosenPeople($setting, $people) as $recipient) {
            $telegram[$recipient->getKey()] = $recipient;
        }

        return [
            'telegram' => collect(array_values($telegram)),
            'phones' => $uncoveredPhones,
            'emails' => $uncoveredEmails,
            'users' => $users,
        ];
    }

    /**
     * Personas (personal y externos) vinculadas y habilitadas para recibir ahora.
     *
     * @return Collection<int, TelegramRecipient>
     */
    public static function linkedPeople(): Collection
    {
        return TelegramRecipient::query()
            ->with('user:id,name,email,phone,status')
            ->whereIn('type', [TelegramRecipientType::User->value, TelegramRecipientType::External->value])
            ->where('status', TelegramRecipientStatus::Active->value)
            ->whereNotNull('chat_id')
            ->get()
            ->filter(static fn (TelegramRecipient $recipient): bool => $recipient->canReceive())
            ->values();
    }

    /**
     * Personas elegidas a mano en el Centro de notificaciones que hoy pueden recibir.
     *
     * @param  Collection<int, TelegramRecipient>|null  $people
     * @return list<TelegramRecipient>
     */
    public static function chosenPeople(SystemNotificationRecipientSetting $setting, ?Collection $people = null): array
    {
        $ids = $setting->telegramRecipientIds();

        if ($ids === []) {
            return [];
        }

        $people ??= self::linkedPeople();

        return $people->filter(static fn (TelegramRecipient $recipient): bool => in_array((int) $recipient->getKey(), $ids, true))->values()->all();
    }

    /**
     * Últimos 10 dígitos: 04141234567, +58 414-1234567 y 584141234567 son el mismo número.
     */
    public static function phoneKey(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }
}
