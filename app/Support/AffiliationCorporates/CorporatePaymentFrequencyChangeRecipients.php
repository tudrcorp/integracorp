<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Enums\SystemNotificationKey;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\SystemNotificationRecipients;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * A quién se avisa de un cambio de frecuencia de pago (o de su reverso):
 *
 * - los contactos del departamento configurados en el Centro de notificaciones;
 * - todos los usuarios ACTIVOS con ADMINISTRACION en su departamento;
 * - en un reverso, además, el analista que hizo el cambio.
 *
 * El interruptor del Centro de notificaciones pausa correo y WhatsApp, pero
 * nunca la notificación del panel: el registro siempre debe llegar a Administración.
 */
final class CorporatePaymentFrequencyChangeRecipients
{
    public const DEPARTMENT = 'ADMINISTRACION';

    /**
     * @param  list<int>  $extraUserIds
     * @return array{channels_active: bool, emails: list<string>, phones: list<string>, users: Collection<int, User>}
     */
    public static function resolve(array $extraUserIds = []): array
    {
        $key = SystemNotificationKey::CorporatePaymentFrequencyChange;
        $channelsActive = self::channelsActive($key);

        $users = self::administrationUsers();
        $extraUserIds = array_values(array_filter(array_map('intval', $extraUserIds), fn (int $id): bool => $id > 0));

        if ($extraUserIds !== []) {
            $extra = User::query()
                ->whereIn('id', $extraUserIds)
                ->where('status', 'ACTIVO')
                ->get();

            $users = $users->merge($extra)->unique('id')->values();
        }

        if (! $channelsActive) {
            return ['channels_active' => false, 'emails' => [], 'phones' => [], 'users' => $users];
        }

        $emails = [...SystemNotificationRecipients::emails($key), ...$users->pluck('email')->all()];
        $phones = [...SystemNotificationRecipients::phones($key), ...$users->pluck('phone')->all()];

        return [
            'channels_active' => true,
            'emails' => self::uniqueEmails($emails),
            'phones' => self::uniquePhones($phones),
            'users' => $users,
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public static function administrationUsers(): Collection
    {
        return User::query()
            ->where('status', 'ACTIVO')
            ->where('departament', 'like', '%'.self::DEPARTMENT.'%')
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => in_array(self::DEPARTMENT, self::departmentsOf($user), true))
            ->values();
    }

    /**
     * Departamentos del usuario leídos del valor crudo: hay registros guardados
     * como texto plano (`ADMINISTRACION`) que el cast a array devuelve vacío.
     *
     * @return list<string>
     */
    public static function departmentsOf(User $user): array
    {
        $raw = $user->getAttributes()['departament'] ?? null;

        if (is_array($raw)) {
            $values = $raw;
        } else {
            $raw = trim((string) $raw);
            $decoded = $raw !== '' ? json_decode($raw, true) : null;
            $values = is_array($decoded) ? $decoded : ($raw !== '' && ! str_starts_with($raw, '[') ? [$raw] : []);
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $value): string => mb_strtoupper(trim((string) $value), 'UTF-8'),
            $values,
        ))));
    }

    private static function channelsActive(SystemNotificationKey $key): bool
    {
        try {
            return SystemNotificationRecipients::isActive($key);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return list<string>
     */
    private static function uniqueEmails(array $emails): array
    {
        $unique = [];

        foreach ($emails as $email) {
            $email = mb_strtolower(trim((string) $email));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $unique[$email] = $email;
            }
        }

        return array_values($unique);
    }

    /**
     * @param  array<int, mixed>  $phones
     * @return list<string>
     */
    private static function uniquePhones(array $phones): array
    {
        $unique = [];

        foreach ($phones as $phone) {
            $normalized = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp(is_scalar($phone) ? (string) $phone : null);

            if ($normalized !== null) {
                $unique[$normalized] = $normalized;
            }
        }

        return array_values($unique);
    }
}
