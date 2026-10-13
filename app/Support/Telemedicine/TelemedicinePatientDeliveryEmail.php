<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicinePatient;

/**
 * Correo al que pueden salir los documentos clínicos de un paciente: solo el suyo.
 *
 * `email_contact` guarda el correo de contacto de la afiliación (la empresa, el
 * pagador o un asociado). Es un dato de consulta, no un destinatario: usarlo como
 * respaldo mandaba informes, récipes y órdenes de un paciente al buzón de la
 * empresa (p. ej. proveedoresdenegocio@mapfre.com.ve en los afiliados de MAPFRE
 * sin correo propio). Sin correo propio no se envía nada por esta vía y el
 * analista lo escribe a mano.
 */
final class TelemedicinePatientDeliveryEmail
{
    /**
     * @param  TelemedicinePatient|array<string, mixed>|null  $patient
     */
    public static function for(TelemedicinePatient|array|null $patient): ?string
    {
        $email = $patient instanceof TelemedicinePatient ? $patient->email : ($patient['email'] ?? null);

        return self::normalize($email);
    }

    public static function normalize(mixed $email): ?string
    {
        if (! is_scalar($email)) {
            return null;
        }

        $email = trim((string) $email);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}
