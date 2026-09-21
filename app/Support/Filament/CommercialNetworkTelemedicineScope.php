<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class CommercialNetworkTelemedicineScope
{
    public const DISCHARGED_STATUS = 'ALTA MEDICA';

    /**
     * Columnas mínimas del paciente para listar un caso y poder autorizar la ficha.
     * Deben incluir las FK de afiliación: un select incompleto deja `canView` en 403.
     *
     * @var list<string>
     */
    public const PATIENT_CASE_EAGER_COLUMNS = [
        'id',
        'full_name',
        'nro_identificacion',
        'code_affiliation',
        'afilliation_id',
        'afilliation_corporate_id',
    ];

    public static function applyToPatients(Builder $query, ?User $user): Builder
    {
        if (! CommercialNetworkAccess::isCommercialNetworkUser($user)) {
            return $query->whereRaw('1 = 0');
        }

        return self::constrainPatientsByAffiliation($query, $user);
    }

    public static function applyToCases(Builder $query, ?User $user, bool $excludeDischarged = true): Builder
    {
        if (! CommercialNetworkAccess::isCommercialNetworkUser($user)) {
            return $query->whereRaw('1 = 0');
        }

        if ($excludeDischarged) {
            $query->where('status', '!=', self::DISCHARGED_STATUS);
        }

        return $query->whereHas(
            'telemedicinePatient',
            fn (Builder $patientQuery): Builder => self::constrainPatientsByAffiliation($patientQuery, $user)
        );
    }

    public static function patientCaseRelationEagerLoad(): string
    {
        return 'telemedicinePatient:'.implode(',', self::PATIENT_CASE_EAGER_COLUMNS);
    }

    public static function userCanAccessPatient(User $user, TelemedicinePatient $patient): bool
    {
        if (! CommercialNetworkAccess::canViewPatientsOrCases($user)) {
            return false;
        }

        $affiliationsAlreadyLoaded = $patient->relationLoaded('afilliation')
            && $patient->relationLoaded('afilliationCorporate');

        if ($affiliationsAlreadyLoaded) {
            return self::affiliationBelongsToUser($patient->getRelation('afilliation'), $user)
                || self::affiliationBelongsToUser($patient->getRelation('afilliationCorporate'), $user);
        }

        if (
            ! array_key_exists('afilliation_id', $patient->getAttributes())
            || ! array_key_exists('afilliation_corporate_id', $patient->getAttributes())
        ) {
            $keys = TelemedicinePatient::query()
                ->select(['id', 'afilliation_id', 'afilliation_corporate_id'])
                ->find($patient->getKey());

            if ($keys === null) {
                return false;
            }

            $patient->forceFill([
                'afilliation_id' => $keys->afilliation_id,
                'afilliation_corporate_id' => $keys->afilliation_corporate_id,
            ]);
        }

        $patient->load([
            'afilliation:id,agent_id,code_agency,owner_code',
            'afilliationCorporate:id,agent_id,code_agency,owner_code',
        ]);

        return self::affiliationBelongsToUser($patient->getRelation('afilliation'), $user)
            || self::affiliationBelongsToUser($patient->getRelation('afilliationCorporate'), $user);
    }

    public static function userCanAccessCase(User $user, TelemedicineCase $case, bool $allowDischarged = false): bool
    {
        if (! $allowDischarged && self::isDischarged($case)) {
            return false;
        }

        $patient = $case->telemedicinePatient;

        if ($patient === null) {
            if (! $case->relationLoaded('telemedicinePatient')) {
                $case->loadMissing('telemedicinePatient');
            }
            $patient = $case->getRelation('telemedicinePatient');
        }

        if (! $patient instanceof TelemedicinePatient) {
            return false;
        }

        return self::userCanAccessPatient($user, $patient);
    }

    public static function isDischarged(TelemedicineCase $case): bool
    {
        return mb_strtoupper(trim((string) $case->status)) === self::DISCHARGED_STATUS;
    }

    public static function constrainPatientsByAffiliation(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $outer) use ($user): void {
            $outer
                ->whereHas(
                    'afilliation',
                    fn (Builder $affiliationQuery): Builder => self::constrainAffiliationQuery($affiliationQuery, $user)
                )
                ->orWhereHas(
                    'afilliationCorporate',
                    fn (Builder $affiliationQuery): Builder => self::constrainAffiliationQuery($affiliationQuery, $user)
                );
        });
    }

    public static function constrainAffiliationQuery(Builder $query, User $user): Builder
    {
        if ((bool) $user->is_agent && filled($user->agent_id)) {
            return $query->where('agent_id', $user->agent_id);
        }

        $codeAgency = trim((string) $user->code_agency);

        if ((bool) $user->is_agency && $user->agency_type === 'MASTER' && $codeAgency !== '') {
            return $query->where('owner_code', $codeAgency);
        }

        if ((bool) $user->is_agency && $user->agency_type === 'GENERAL' && $codeAgency !== '') {
            return $query->where('code_agency', $codeAgency);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function affiliationBelongsToUser(mixed $affiliation, User $user): bool
    {
        if ($affiliation === null || ! is_object($affiliation)) {
            return false;
        }

        if ((bool) $user->is_agent) {
            return filled($user->agent_id)
                && (int) ($affiliation->agent_id ?? 0) === (int) $user->agent_id;
        }

        $codeAgency = trim((string) $user->code_agency);

        if ($codeAgency === '') {
            return false;
        }

        if ((bool) $user->is_agency && $user->agency_type === 'MASTER') {
            return (string) ($affiliation->owner_code ?? '') === $codeAgency;
        }

        if ((bool) $user->is_agency && $user->agency_type === 'GENERAL') {
            return (string) ($affiliation->code_agency ?? '') === $codeAgency;
        }

        return false;
    }
}
