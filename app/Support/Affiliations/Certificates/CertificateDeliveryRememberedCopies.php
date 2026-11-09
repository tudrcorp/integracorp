<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\User;
use InvalidArgumentException;

/**
 * Correos testigos (CC) y copias ocultas (CCO) que cada analista pidió recordar
 * para sus próximos envíos del Generador de Certificado (individual y
 * corporativo). Se guardan en `users.ui_preferences`, no en el navegador, para
 * que lo acompañen en cualquier equipo.
 *
 * De la copia oculta solo se recuerdan los correos que agregó el analista: la
 * fija ({@see CertificateDeliveryRecipients::MANDATORY_BCC}) va siempre igual.
 */
final class CertificateDeliveryRememberedCopies
{
    public const KIND_CC = 'cc';

    public const KIND_BCC = 'bcc';

    private const PREFERENCE_KEYS = [
        self::KIND_CC => 'certificate_delivery_cc',
        self::KIND_BCC => 'certificate_delivery_bcc',
    ];

    public static function preferenceKey(string $kind): string
    {
        return self::PREFERENCE_KEYS[$kind] ?? throw new InvalidArgumentException("Tipo de copia desconocido: {$kind}");
    }

    /**
     * @return list<string>
     */
    public static function for(?User $user, string $kind): array
    {
        $key = self::preferenceKey($kind);

        if ($user === null) {
            return [];
        }

        $stored = ($user->ui_preferences ?? [])[$key] ?? [];

        return self::limit($kind, is_array($stored) ? $stored : []);
    }

    /**
     * Guarda la lista (reemplaza la anterior). Una lista vacía o `$remember`
     * apagado olvida lo recordado.
     *
     * @param  iterable<mixed>  $emails
     */
    public static function update(User $user, string $kind, iterable $emails, bool $remember): void
    {
        $key = self::preferenceKey($kind);
        $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];
        $clean = $remember ? self::limit($kind, $emails) : [];

        if ($clean === []) {
            if (! array_key_exists($key, $preferences)) {
                return;
            }

            unset($preferences[$key]);
        } else {
            if (($preferences[$key] ?? null) === $clean) {
                return;
            }

            $preferences[$key] = $clean;
        }

        // Preferencia de interfaz: no es un cambio de datos del usuario que deba auditarse.
        $user->forceFill(['ui_preferences' => $preferences === [] ? null : $preferences])->saveQuietly();
    }

    /**
     * Correos válidos, sin repetir y dentro del tope; en la copia oculta, sin la fija.
     *
     * @param  iterable<mixed>  $emails
     * @return list<string>
     */
    private static function limit(string $kind, iterable $emails): array
    {
        $clean = CertificateDeliveryRecipients::clean($emails);
        $max = CertificateDeliveryRecipients::MAX_COPIES;

        if ($kind === self::KIND_BCC) {
            $clean = array_values(array_diff($clean, CertificateDeliveryRecipients::MANDATORY_BCC));
            $max -= count(CertificateDeliveryRecipients::MANDATORY_BCC);
        }

        return array_slice($clean, 0, $max);
    }
}
