<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineConsultationPatient;

/**
 * Referencia `REF-xxxxx` de una consulta o seguimiento.
 *
 * Da nombre al PDF del informe ({cédula}-{referencia}-...), así que una referencia
 * repetida en el mismo paciente haría que un documento pise a otro.
 */
final class TelemedicineConsultationReference
{
    public const PREFIX = 'REF-';

    private const MAX_ATTEMPTS = 50;

    public static function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = self::PREFIX.random_int(11111, 99999);

            if (! self::exists($candidate)) {
                return $candidate;
            }
        }

        // El rango de 5 dígitos se agota: se alarga en vez de repetir.
        do {
            $candidate = self::PREFIX.random_int(100000, 9999999);
        } while (self::exists($candidate));

        return $candidate;
    }

    /**
     * Conserva la referencia que el usuario vio en pantalla si sigue libre;
     * si alguien más la tomó mientras llenaba el formulario, emite otra.
     */
    public static function ensureUnique(?string $candidate): string
    {
        $candidate = trim((string) $candidate);

        return $candidate !== '' && ! self::exists($candidate)
            ? $candidate
            : self::generate();
    }

    public static function exists(string $reference): bool
    {
        return TelemedicineConsultationPatient::query()
            ->where('code_reference', $reference)
            ->exists();
    }
}
