<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineServiceList;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Restricciones de listado de casos de telemedicina (panel médico / recurso Filament).
 */
final class TelemedicineCaseFilamentListQuery
{
    /**
     * Catálogo: servicio derivado «Traslado en ambulancia» (id histórico en BD).
     */
    public const TRASLADO_EN_AMBULANCIA_DRIFT_SERVICE_LIST_ID = 3;

    /**
     * Casos que nacen en Operaciones sin médico ni equipo (retail sin telemedicina
     * ni AMD, registro directo): heredan `managed_by` = TDG del paciente, pero no
     * son trabajo de ningún médico y no deben entrar al pool TDG.
     *
     * @var list<string>
     */
    public const OPERATIONS_ONLY_CASE_STATUSES = ['RETAIL', 'REGISTRO DIRECTO'];

    /**
     * Aplica filtros al listado del recurso «Casos de telemedicina» (misma línea visual que el widget del escritorio).
     *
     * - Médico TDG ({@see TelemedicineDoctor::$managed_by} = TDG): todos los casos de médicos TDG con estado distinto de ALTA MEDICA.
     * - Con {@see User::$doctor_id} (resto): los casos de su equipo de guardia, ver {@see self::constrainToDoctorTeamCases()}.
     * - Contexto ATENMEDI (departamento usuario o médico vinculado con {@see TelemedicineDoctor::$managed_by} = ATENMEDI): solo casos con {@see TelemedicineCase::$managed_by} = ATENMEDI.
     * - Oculta casos en alta médica a nivel caso.
     * - Solo en contexto ATENMEDI: oculta casos con alguna consulta en ALTA MEDICA o con traslado en ambulancia en servicio principal o derivado.
     */
    public static function applyTelemedicinaResourceCasesConstraints(Builder $query): Builder
    {
        $user = Auth::user();

        $query->where('status', '!=', 'ALTA MEDICA');

        if ($user instanceof User && self::userIsInTdgTelemedicinaContext($user)) {
            self::constrainToTdgDoctorsCases($query);

            return $query;
        }

        if ($user instanceof User && $user->doctor_id !== null) {
            self::constrainToDoctorTeamCases($query, (int) $user->doctor_id);
        }

        if ($user !== null && self::userIsInAtenmediTelemedicinaContext($user)) {
            $query->where('managed_by', 'ATENMEDI');
            self::excludeCasesHavingConsultationWithAltaMedica($query);
            self::excludeCasesHavingConsultationWithTrasladoAmbulancia($query);
        }

        return $query;
    }

    /**
     * Widget del escritorio (panel telemedicina).
     *
     * - Médico TDG ({@see TelemedicineDoctor::$managed_by} = TDG): todos los casos de médicos TDG con estado distinto de ALTA MEDICA.
     * - Contexto ATENMEDI: casos del equipo del médico en sesión y {@see TelemedicineCase::$managed_by} = ATENMEDI; excluye traslado en ambulancia en última consulta.
     * - Resto: casos del equipo de guardia del médico en sesión, sin alta médica a nivel caso.
     */
    public static function applyDashboardWidgetCaseConstraints(Builder $query): Builder
    {
        return self::applyDashboardScope($query, includeDischarged: false);
    }

    /**
     * Casos que ve el médico en su dashboard. Las tarjetas de estadísticas usan
     * el mismo alcance que la tabla (con altas, que la tabla oculta) para que
     * los números coincidan con lo que el médico tiene en pantalla.
     *
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function applyDashboardScope(Builder $query, bool $includeDischarged = false): Builder
    {
        $user = Auth::user();

        if ($user === null || ! $user instanceof User || $user->doctor_id === null) {
            return $query->whereRaw('0 = 1');
        }

        if (! $includeDischarged) {
            $query->where('status', '!=', 'ALTA MEDICA');
        }

        if (self::userIsInTdgTelemedicinaContext($user)) {
            self::constrainToTdgDoctorsCases($query);

            return $query->with(['telemedicineDoctor', 'priority']);
        }

        self::constrainToDoctorTeamCases($query, (int) $user->doctor_id);

        if (self::userIsInAtenmediTelemedicinaContext($user)) {
            $query->where('managed_by', 'ATENMEDI');
        }

        self::excludeCasesWhereLatestConsultationDriftIsTrasladoAmbulanciaForAtenmediDoctor($query);

        return $query->with(['priority', 'telemedicineDoctor']);
    }

    /**
     * Bitácora del panel médico: mismos casos del equipo que el recurso,
     * incluyendo alta médica para poder descargar el expediente cerrado.
     *
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function applyTelemedicinaBitacoraConstraints(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('0 = 1');
        }

        if (self::userIsInTdgTelemedicinaContext($user)) {
            return self::constrainToTdgDoctorsCases($query);
        }

        if ($user->doctor_id !== null) {
            self::constrainToDoctorTeamCases($query, (int) $user->doctor_id);
        }

        if (self::userIsInAtenmediTelemedicinaContext($user)) {
            $query->where('managed_by', 'ATENMEDI');
        }

        return $query;
    }

    /**
     * Buscador global del panel médico: el mismo alcance de la Bitácora, altas
     * médicas incluidas, para que el médico encuentre también a un paciente que
     * regresa. Ver {@see TelemedicineCaseGlobalSearch}.
     *
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function applyTelemedicinaGlobalSearchConstraints(Builder $query): Builder
    {
        return self::applyTelemedicinaBitacoraConstraints($query);
    }

    /**
     * Casos del pool TDG: gestión TDG, asignados a un médico con {@see TelemedicineDoctor::$managed_by} = TDG
     * o asignados al Equipo Médico TDG ({@see TelemedicineMedicalTeam}), salvo los
     * casos solo de Operaciones ({@see self::OPERATIONS_ONLY_CASE_STATUSES}) sin médico ni equipo.
     */
    public static function constrainToTdgDoctorsCases(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $tdgCases): void {
                $tdgCases
                    ->where('managed_by', 'TDG')
                    ->orWhereHas('telemedicineDoctor', function (Builder $doctor): void {
                        $doctor->where('managed_by', 'TDG');
                    })
                    ->orWhere(function (Builder $assignedToTeam): void {
                        $assignedToTeam
                            ->where('assigned_to_medical_team', true)
                            ->whereNull('medical_team_supplier_id');
                    });
            })
            ->where(function (Builder $forDoctors): void {
                $forDoctors
                    ->whereNull($forDoctors->qualifyColumn('status'))
                    ->orWhereNotIn($forDoctors->qualifyColumn('status'), self::OPERATIONS_ONLY_CASE_STATUSES)
                    ->orWhereNotNull($forDoctors->qualifyColumn('telemedicine_doctor_id'))
                    ->orWhere($forDoctors->qualifyColumn('assigned_to_medical_team'), true);
            });
    }

    /**
     * Casos del equipo de guardia del médico: un caso no es de un médico sino de
     * su equipo, porque al cambiar la guardia el médico entrante debe poder
     * tomar y gestionar el caso que asignaron a su colega.
     *
     * - Médico TDG ({@see TelemedicineDoctor::$managed_by} = TDG): el pool TDG, ver {@see self::constrainToTdgDoctorsCases()}.
     * - Médico de un proveedor ({@see TelemedicineDoctor::$supplier_id}): los casos asignados a cualquier médico de ese mismo proveedor
     *   o al Equipo Médico de ese proveedor ({@see TelemedicineMedicalTeam}).
     *   No se usa `managed_by`, que guarda el nombre comercial completo del proveedor y no es una clave.
     * - Médico sin TDG ni proveedor: solo sus propios casos.
     *
     * @param  Builder<TelemedicineCase>  $query
     * @return Builder<TelemedicineCase>
     */
    public static function constrainToDoctorTeamCases(Builder $query, int $doctorId): Builder
    {
        $doctor = TelemedicineDoctor::query()
            ->select(['id', 'managed_by', 'supplier_id'])
            ->find($doctorId);

        if ($doctor !== null && strtoupper(trim((string) $doctor->managed_by)) === 'TDG') {
            return self::constrainToTdgDoctorsCases($query);
        }

        $supplierId = $doctor?->supplier_id;

        if ($supplierId !== null) {
            return $query->where(function (Builder $teamCases) use ($supplierId): void {
                $teamCases
                    ->whereHas('telemedicineDoctor', function (Builder $teamDoctor) use ($supplierId): void {
                        $teamDoctor->where('supplier_id', $supplierId);
                    })
                    ->orWhere(function (Builder $assignedToTeam) use ($supplierId): void {
                        $assignedToTeam
                            ->where('assigned_to_medical_team', true)
                            ->where('medical_team_supplier_id', $supplierId);
                    });
            });
        }

        return $query->where('telemedicine_doctor_id', $doctorId);
    }

    /**
     * Si el médico en sesión puede gestionar el caso: con la misma regla de equipo
     * que los listados, para que lo que el médico ve en pantalla sea lo que puede atender.
     */
    public static function caseBelongsToUserDoctorTeam(mixed $user, TelemedicineCase $case): bool
    {
        if (! $user instanceof User || $user->doctor_id === null) {
            return false;
        }

        $doctorId = (int) $user->doctor_id;

        if ((int) $case->telemedicine_doctor_id === $doctorId) {
            return true;
        }

        return self::constrainToDoctorTeamCases(TelemedicineCase::query()->whereKey($case->getKey()), $doctorId)->exists();
    }

    /**
     * TDG puede abrir y editar el caso salvo que esté bajo un médico o gestión ATENMEDI.
     */
    public static function dashboardUserCanInteractWithCase(mixed $user, TelemedicineCase $case): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! self::userIsInTdgTelemedicinaContext($user)) {
            return true;
        }

        return ! self::caseIsUnderAtenmediDoctor($case);
    }

    public static function notifyTdgCaseUnderAtenmediDoctor(TelemedicineCase $case): void
    {
        $doctorName = self::atenmediDoctorDisplayNameForCase($case);

        Notification::make()
            ->title('Caso en manos de ATENMEDI')
            ->body("Este caso está a cargo del doctor {$doctorName} de ATENMEDI. No puede editarlo desde TDG.")
            ->warning()
            ->send();
    }

    public static function userIsInTdgTelemedicinaContext(mixed $user): bool
    {
        if (! $user instanceof User || $user->doctor_id === null) {
            return false;
        }

        return TelemedicineDoctor::query()
            ->whereKey($user->doctor_id)
            ->where('managed_by', 'TDG')
            ->exists();
    }

    public static function caseIsUnderAtenmediDoctor(TelemedicineCase $case): bool
    {
        if (strtoupper(trim((string) $case->managed_by)) === 'ATENMEDI') {
            return true;
        }

        if (! $case->relationLoaded('telemedicineDoctor')) {
            $case->loadMissing('telemedicineDoctor');
        }

        $doctor = $case->telemedicineDoctor;

        return $doctor !== null
            && strtoupper(trim((string) $doctor->managed_by)) === 'ATENMEDI';
    }

    public static function atenmediDoctorDisplayNameForCase(TelemedicineCase $case): string
    {
        if (! $case->relationLoaded('telemedicineDoctor')) {
            $case->loadMissing('telemedicineDoctor');
        }

        $name = trim((string) ($case->telemedicineDoctor?->full_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        return 'ATENMEDI';
    }

    /**
     * ATENMEDI: no permitir flujo de actualización cuando el derivado es traslado en ambulancia.
     */
    public static function atenmediUserBlockedFromUpdatingConsultation(mixed $user, ?TelemedicineConsultationPatient $consultation): bool
    {
        if ($consultation === null) {
            return false;
        }

        if (! self::userIsInAtenmediTelemedicinaContext($user)) {
            return false;
        }

        if ((int) ($consultation->telemedicine_service_list_drift_id ?? 0) === self::TRASLADO_EN_AMBULANCIA_DRIFT_SERVICE_LIST_ID) {
            return true;
        }

        if (! $consultation->relationLoaded('telemedicineServiceListDrift')) {
            $consultation->loadMissing('telemedicineServiceListDrift');
        }

        return self::driftServiceNameIndicatesTrasladoAmbulancia($consultation->telemedicineServiceListDrift?->name);
    }

    public static function userDepartmentsIncludeAtenmedi(mixed $user): bool
    {
        return in_array('ATENMEDI', self::normalizedUserDepartments($user), true);
    }

    public static function userIsInAtenmediTelemedicinaContext(mixed $user): bool
    {
        if (self::userDepartmentsIncludeAtenmedi($user)) {
            return true;
        }

        if (! $user instanceof User || $user->doctor_id === null) {
            return false;
        }

        return TelemedicineDoctor::query()
            ->whereKey($user->doctor_id)
            ->where('managed_by', 'ATENMEDI')
            ->exists();
    }

    /**
     * @return list<string>
     */
    public static function normalizedUserDepartments(mixed $user): array
    {
        if (! is_object($user)) {
            return [];
        }

        $raw = data_get($user, 'departament');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = strtoupper(trim($item));
            }
        }

        return $out;
    }

    private static function excludeCasesHavingConsultationWithAltaMedica(Builder $query): void
    {
        $query->whereDoesntHave('consultations', function (Builder $consultations): void {
            $consultations->where('status', 'ALTA MEDICA');
        });
    }

    private static function excludeCasesHavingConsultationWithTrasladoAmbulancia(Builder $query): void
    {
        $query->whereDoesntHave('consultations', function (Builder $consultations): void {
            $consultations->where(function (Builder $w): void {
                $w->where('telemedicine_service_list_drift_id', self::TRASLADO_EN_AMBULANCIA_DRIFT_SERVICE_LIST_ID)
                    ->orWhereHas('telemedicineServiceListDrift', function (Builder $drift): void {
                        $drift->whereRaw('UPPER(name) LIKE ?', ['%TRASLADO%AMBULANCIA%']);
                    })
                    ->orWhereHas('telemedicineServiceList', function (Builder $main): void {
                        $main->whereRaw('UPPER(name) LIKE ?', ['%TRASLADO%AMBULANCIA%']);
                    });
            });
        });
    }

    private static function excludeCasesWhereLatestConsultationDriftIsTrasladoAmbulanciaForAtenmediDoctor(Builder $query): void
    {
        if (! self::userIsInAtenmediTelemedicinaContext(Auth::user())) {
            return;
        }

        $caseTable = (new TelemedicineCase)->getTable();
        $consultTable = (new TelemedicineConsultationPatient)->getTable();
        $driftTable = (new TelemedicineServiceList)->getTable();

        $query->whereNotExists(function ($sub) use ($caseTable, $consultTable, $driftTable): void {
            $sub->selectRaw('1')
                ->from("{$consultTable} as lc")
                ->whereColumn('lc.telemedicine_case_id', "{$caseTable}.id")
                ->whereRaw("lc.id = (select max(cmx.id) from {$consultTable} cmx where cmx.telemedicine_case_id = {$caseTable}.id)")
                ->where(function ($w) use ($driftTable): void {
                    $w->where('lc.telemedicine_service_list_drift_id', self::TRASLADO_EN_AMBULANCIA_DRIFT_SERVICE_LIST_ID)
                        ->orWhereExists(function ($ex) use ($driftTable): void {
                            $ex->selectRaw('1')
                                ->from("{$driftTable} as tsl")
                                ->whereColumn('tsl.id', 'lc.telemedicine_service_list_drift_id')
                                ->whereRaw('UPPER(tsl.name) LIKE ?', ['%TRASLADO%AMBULANCIA%']);
                        });
                });
        });
    }

    public static function driftServiceNameIndicatesTrasladoAmbulancia(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        $normalized = strtoupper(Str::ascii(trim($name)));

        return str_contains($normalized, 'TRASLADO EN AMBULANCIA');
    }
}
