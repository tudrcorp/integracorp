<?php

declare(strict_types=1);

namespace App\Support\Telemedicine\ClinicalRecord;

use App\Models\ClinicalRecordAccessLog;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Quién puede leer la ficha clínica y registro de cada lectura.
 *
 * - Telemedicina: el médico (o su equipo) que tiene o tuvo un caso del paciente.
 * - Operaciones: analistas TDG activos con correo interno y superadmin. Nunca usuarios de
 *   proveedores, AMD ni ATENMEDI, aunque entren al panel.
 */
final class ClinicalRecordAccess
{
    public const PANEL_TELEMEDICINE = 'telemedicina';

    public const PANEL_OPERATIONS = 'operations';

    /** Ficha clínica: todo lo clínico, sin afiliación ni cupos. */
    public const KIND_CLINICAL = 'clinica';

    /** Ficha técnica (uso interno): la clínica más afiliación, cobertura y cupos. Solo Operaciones. */
    public const KIND_TECHNICAL = 'tecnica';

    public static function canView(?User $user, TelemedicinePatient $patient, string $panel, string $kind = self::KIND_CLINICAL): bool
    {
        if (! $user instanceof User || strtoupper((string) $user->status) !== 'ACTIVO') {
            return false;
        }

        if ($kind === self::KIND_TECHNICAL && $panel !== self::PANEL_OPERATIONS) {
            return false;
        }

        return match ($panel) {
            self::PANEL_TELEMEDICINE => self::doctorTeamTreats($user, $patient),
            self::PANEL_OPERATIONS => self::isTdgAnalyst($user),
            default => false,
        };
    }

    /**
     * Sin paciente concreto: para mostrar u ocultar la acción en una tabla sin una consulta por fila.
     */
    public static function canUsePanel(?User $user, string $panel, string $kind = self::KIND_CLINICAL): bool
    {
        if (! $user instanceof User || strtoupper((string) $user->status) !== 'ACTIVO') {
            return false;
        }

        if ($kind === self::KIND_TECHNICAL && $panel !== self::PANEL_OPERATIONS) {
            return false;
        }

        return match ($panel) {
            self::PANEL_TELEMEDICINE => $user->doctor_id !== null,
            self::PANEL_OPERATIONS => self::isTdgAnalyst($user),
            default => false,
        };
    }

    public static function isTdgAnalyst(User $user): bool
    {
        if ($user->supplier_id !== null || $user->isProveedorAmd()) {
            return false;
        }

        $departments = array_map('strval', (array) ($user->departament ?? []));

        if (in_array('ATENMEDI', $departments, true)) {
            return false;
        }

        if (in_array('SUPERADMIN', $departments, true)) {
            return true;
        }

        return in_array('OPERACIONES', $departments, true)
            && str_ends_with(strtolower((string) $user->email), '@tudrencasa.com');
    }

    public static function record(User $user, TelemedicinePatient $patient, string $panel, string $action, ?string $ip = null, string $kind = self::KIND_CLINICAL): void
    {
        try {
            if (! Schema::hasTable('clinical_record_access_logs')) {
                return;
            }

            ClinicalRecordAccessLog::query()->create([
                'telemedicine_patient_id' => (int) $patient->getKey(),
                'user_id' => (int) $user->getKey(),
                'user_name' => mb_substr((string) $user->name, 0, 191),
                'panel' => $panel,
                'action' => $kind === self::KIND_TECHNICAL ? $action.'_'.self::KIND_TECHNICAL : $action,
                'ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Ficha clínica: no se pudo registrar la lectura', ['patient' => $patient->getKey(), 'error' => $exception->getMessage()]);
        }
    }

    /**
     * Últimas lecturas de la ficha, para mostrarlas al pie.
     *
     * @return list<array{who: string, panel: string, action: string, at: string}>
     */
    public static function recent(TelemedicinePatient $patient, int $limit = 8): array
    {
        if (! Schema::hasTable('clinical_record_access_logs')) {
            return [];
        }

        return ClinicalRecordAccessLog::query()
            ->where('telemedicine_patient_id', $patient->getKey())
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (ClinicalRecordAccessLog $log): array => [
                'who' => (string) ($log->user_name ?: 'Usuario #'.$log->user_id),
                'panel' => $log->panel === self::PANEL_OPERATIONS ? 'Operaciones' : 'Telemedicina',
                'action' => match ($log->action) {
                    ClinicalRecordAccessLog::ACTION_PDF => 'Descargó el PDF de la ficha clínica',
                    ClinicalRecordAccessLog::ACTION_PDF.'_'.self::KIND_TECHNICAL => 'Descargó el PDF de la ficha técnica',
                    ClinicalRecordAccessLog::ACTION_VIEW.'_'.self::KIND_TECHNICAL => 'Abrió la ficha técnica',
                    default => 'Abrió la ficha clínica',
                },
                'at' => $log->created_at?->format('d/m/Y h:i a') ?? '',
            ])
            ->all();
    }

    private static function doctorTeamTreats(User $user, TelemedicinePatient $patient): bool
    {
        if ($user->doctor_id === null) {
            return false;
        }

        return TelemedicineCaseFilamentListQuery::constrainToDoctorTeamCases(TelemedicineCase::query(), (int) $user->doctor_id)
            ->where('telemedicine_patient_id', $patient->getKey())
            ->exists();
    }
}
