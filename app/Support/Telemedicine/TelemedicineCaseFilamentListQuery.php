<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineServiceList;
use App\Models\User;
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
     * Aplica filtros al listado del recurso «Casos de telemedicina» (misma línea visual que el widget del escritorio).
     *
     * - Con {@see User::$doctor_id}: solo casos asignados a ese médico.
     * - Contexto ATENMEDI (departamento usuario o médico vinculado con {@see TelemedicineDoctor::$managed_by} = ATENMEDI): solo casos con {@see TelemedicineCase::$managed_by} = ATENMEDI.
     * - Oculta casos en alta médica a nivel caso (todos los médicos, p. ej. TDG: siguen viendo el caso hasta que el caso pase a ALTA MEDICA).
     * - Solo en contexto ATENMEDI: oculta casos con alguna consulta en ALTA MEDICA o con traslado en ambulancia en servicio principal o derivado.
     */
    public static function applyTelemedicinaResourceCasesConstraints(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user instanceof User && $user->doctor_id !== null) {
            $query->where('telemedicine_doctor_id', $user->doctor_id);
        }

        if ($user !== null && self::userIsInAtenmediTelemedicinaContext($user)) {
            $query->where('managed_by', 'ATENMEDI');
        }

        $query->where('status', '!=', 'ALTA MEDICA');

        if ($user !== null && self::userIsInAtenmediTelemedicinaContext($user)) {
            self::excludeCasesHavingConsultationWithAltaMedica($query);
            self::excludeCasesHavingConsultationWithTrasladoAmbulancia($query);
        }

        return $query;
    }

    /**
     * Widget del escritorio (panel telemedicina): casos del médico en sesión.
     *
     * - Contexto ATENMEDI (departamento usuario o {@see TelemedicineDoctor::$managed_by} = ATENMEDI): solo {@see TelemedicineCase::$managed_by} = ATENMEDI.
     * - Excluye casos en alta médica a nivel caso.
     * - En ATENMEDI: excluye casos cuya última consulta tenga derivado traslado en ambulancia.
     */
    public static function applyDashboardWidgetCaseConstraints(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user === null || ! $user instanceof User || $user->doctor_id === null) {
            return $query->whereRaw('0 = 1');
        }

        $query
            ->where('telemedicine_doctor_id', $user->doctor_id)
            ->where('status', '!=', 'ALTA MEDICA');

        if (self::userIsInAtenmediTelemedicinaContext($user)) {
            $query->where('managed_by', 'ATENMEDI');
        }

        self::excludeCasesWhereLatestConsultationDriftIsTrasladoAmbulanciaForAtenmediDoctor($query);

        return $query;
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
