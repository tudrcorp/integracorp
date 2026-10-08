<?php

declare(strict_types=1);

namespace App\Support\Renovations;

use App\Enums\SystemNotificationKey;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChangeRecipients;
use App\Support\SystemNotificationRecipients;
use Illuminate\Database\Eloquent\Collection;

/**
 * A quién se avisa de una renovación anticipada:
 *
 * - todos los usuarios ACTIVOS con SUPERADMIN en su departamento (correo y teléfono
 *   de su usuario);
 * - los contactos adicionales configurados en el Centro de notificaciones
 *   («Renovación anticipada»).
 *
 * Si la alerta está inactiva en el Centro de notificaciones no se avisa a nadie:
 * la renovación igual queda registrada en el histórico y en la auditoría.
 */
final class EarlyRenovationRecipients
{
    public const DEPARTMENT = 'SUPERADMIN';

    /**
     * @return array{active: bool, emails: list<string>, phones: list<string>, superadmins: int}
     */
    public static function resolve(): array
    {
        $key = SystemNotificationKey::EarlyRenovationAcceptance;

        if (! SystemNotificationRecipients::isActive($key)) {
            return ['active' => false, 'emails' => [], 'phones' => [], 'superadmins' => 0];
        }

        $users = self::superAdmins();

        return [
            'active' => true,
            'emails' => self::uniqueEmails([...$users->pluck('email')->all(), ...SystemNotificationRecipients::emails($key)]),
            'phones' => self::uniquePhones([...$users->pluck('phone')->all(), ...SystemNotificationRecipients::phones($key)]),
            'superadmins' => $users->count(),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public static function superAdmins(): Collection
    {
        return User::query()
            ->where('status', 'ACTIVO')
            ->where('departament', 'like', '%'.self::DEPARTMENT.'%')
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => in_array(self::DEPARTMENT, CorporatePaymentFrequencyChangeRecipients::departmentsOf($user), true))
            ->values();
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
