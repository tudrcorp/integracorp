<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\User;

/**
 * Médico que FIRMA la consulta o el seguimiento.
 *
 * No es el médico asignado al caso, y confundirlos fue un error real: en el pool
 * TDG cualquier médico con `TelemedicineDoctor::$managed_by` = TDG abre y atiende
 * el caso de otro (ver {@see TelemedicineCaseFilamentListQuery::constrainToTdgDoctorsCases()}),
 * así que tomar el firmante de `telemedicine_cases.telemedicine_doctor_id`
 * estampaba en el informe, la receta y las órdenes el sello de quien no atendió.
 * No era solo un PDF con la firma cambiada: la autoría quedaba mal atribuida
 * también en `telemedicine_patient_medications`, `_labs`, `_study`, `_specialty`
 * y en las coordinaciones de Operaciones, que heredan este mismo id.
 *
 * El firmante es siempre el médico vinculado al usuario en sesión
 * (`users.doctor_id`). La asignación del caso vive aparte y no se toca.
 */
final class TelemedicineConsultationSigningDoctor
{
    public const MISSING_DOCTOR_MESSAGE = 'Su usuario no tiene un médico vinculado y todo documento de telemedicina se firma con el sello de quien atiende. Pida al administrador que asocie su usuario a su ficha de médico antes de registrar la consulta.';

    /**
     * Caché por request: el formulario resuelve el firmante en cada render del
     * esquema. La clave es el usuario, nunca la firma, así que un cambio de sello
     * se ve en el siguiente documento sin vaciar nada.
     *
     * @var array<int, int|null>
     */
    private static array $doctorIdByUserId = [];

    public static function forUser(mixed $user): ?TelemedicineDoctor
    {
        $doctorId = self::idForUser($user);

        return $doctorId === null
            ? null
            : TelemedicineDoctor::query()->find($doctorId);
    }

    public static function idForUser(mixed $user): ?int
    {
        if (! $user instanceof User) {
            return null;
        }

        return self::idForUserId((int) $user->id);
    }

    public static function idForUserId(?int $userId): ?int
    {
        if ($userId === null || $userId < 1) {
            return null;
        }

        if (array_key_exists($userId, self::$doctorIdByUserId)) {
            return self::$doctorIdByUserId[$userId];
        }

        $doctorId = (int) (User::query()->whereKey($userId)->value('doctor_id') ?? 0);

        $linked = $doctorId > 0
            && TelemedicineDoctor::query()->whereKey($doctorId)->exists();

        return self::$doctorIdByUserId[$userId] = $linked ? $doctorId : null;
    }

    /**
     * Valor inicial del formulario. El médico del caso entra solo como respaldo
     * visual mientras el usuario no tenga ficha vinculada; el firmante definitivo
     * se fija en el servidor al guardar, en
     * {@see \App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient::mutateFormDataBeforeCreate()}.
     */
    public static function defaultIdForForm(mixed $user, mixed $case): ?int
    {
        $doctorId = self::idForUser($user);

        if ($doctorId !== null) {
            return $doctorId;
        }

        if (! $case instanceof TelemedicineCase) {
            return null;
        }

        $caseDoctorId = (int) ($case->telemedicine_doctor_id ?? 0);

        return $caseDoctorId > 0 ? $caseDoctorId : null;
    }

    /**
     * Solo para tests: la caché vive un request y no debe cruzarse entre casos.
     */
    public static function flush(): void
    {
        self::$doctorIdByUserId = [];
    }
}
