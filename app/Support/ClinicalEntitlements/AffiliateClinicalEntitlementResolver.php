<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Models\Affiliate;
use App\Models\AffiliateClinicalServiceUsage;
use App\Models\AffiliateCorporate;
use App\Models\Plan;
use App\Models\PlanBenefitClinicalSetting;
use App\Models\TelemedicinePatient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class AffiliateClinicalEntitlementResolver
{
    /**
     * @var array<int, ClinicalEntitlementSnapshot>
     */
    private static array $byPatientId = [];

    /**
     * @var array<string, ClinicalEntitlementSnapshot>
     */
    private static array $byHolderKey = [];

    public static function forPatient(TelemedicinePatient $patient): ClinicalEntitlementSnapshot
    {
        $id = (int) ($patient->id ?? 0);
        if ($id > 0 && isset(self::$byPatientId[$id])) {
            return self::$byPatientId[$id];
        }

        $patient->loadMissing(['plan', 'afilliation:id,effective_date', 'afilliationCorporate:id,effective_date']);

        $snapshot = self::resolveForHolder(
            plan: $patient->plan,
            nroIdentificacion: trim((string) $patient->nro_identificacion),
            telemedicinePatientId: $id > 0 ? $id : null,
            effectDate: ClinicalEntitlementWindow::effectDate($patient),
            noPlanMessage: 'Este paciente no tiene un plan asociado. No se pueden asignar servicios clínicos del plan hasta que Operaciones vincule la afiliación.',
        );

        if ($id > 0) {
            self::$byPatientId[$id] = $snapshot;
        }

        return $snapshot;
    }

    public static function forAffiliate(Affiliate $affiliate): ClinicalEntitlementSnapshot
    {
        $id = (int) ($affiliate->id ?? 0);
        $key = 'affiliate:'.$id;
        if ($id > 0 && isset(self::$byHolderKey[$key])) {
            return self::$byHolderKey[$key];
        }

        $affiliate->loadMissing(['plan', 'affiliation:id,effective_date']);

        $snapshot = self::resolveForHolder(
            plan: $affiliate->plan,
            nroIdentificacion: trim((string) $affiliate->nro_identificacion),
            telemedicinePatientId: null,
            effectDate: ClinicalEntitlementWindow::effectDateFromValues(
                $affiliate->affiliation?->effective_date,
                $affiliate->dateInit ?? null,
            ),
            noPlanMessage: 'Este afiliado no tiene un plan asociado. No se puede consultar el uso clínico hasta que Operaciones asigne el plan.',
        );

        if ($id > 0) {
            self::$byHolderKey[$key] = $snapshot;
        }

        return $snapshot;
    }

    public static function forAffiliateCorporate(AffiliateCorporate $affiliate): ClinicalEntitlementSnapshot
    {
        $id = (int) ($affiliate->id ?? 0);
        $key = 'affiliate-corporate:'.$id;
        if ($id > 0 && isset(self::$byHolderKey[$key])) {
            return self::$byHolderKey[$key];
        }

        $affiliate->loadMissing(['plan', 'affiliationCorporate:id,effective_date']);

        $snapshot = self::resolveForHolder(
            plan: $affiliate->plan,
            nroIdentificacion: trim((string) $affiliate->nro_identificacion),
            telemedicinePatientId: null,
            effectDate: ClinicalEntitlementWindow::effectDateFromValues(
                $affiliate->affiliationCorporate?->effective_date,
                $affiliate->initial_date ?? null,
                $affiliate->dateInit ?? null,
            ),
            noPlanMessage: 'Este afiliado corporativo no tiene un plan asociado. No se puede consultar el uso clínico hasta que Operaciones asigne el plan.',
        );

        if ($id > 0) {
            self::$byHolderKey[$key] = $snapshot;
        }

        return $snapshot;
    }

    public static function flush(?int $patientId = null): void
    {
        if ($patientId === null) {
            self::$byPatientId = [];
            self::$byHolderKey = [];

            return;
        }

        unset(self::$byPatientId[$patientId]);
    }

    private static function resolveForHolder(
        mixed $plan,
        string $nroIdentificacion,
        ?int $telemedicinePatientId,
        CarbonImmutable $effectDate,
        string $noPlanMessage,
    ): ClinicalEntitlementSnapshot {
        if (! $plan instanceof Plan || $plan->id === null) {
            return new ClinicalEntitlementSnapshot(
                hasPlan: false,
                isComplete: false,
                missingBenefitLabels: [],
                entitlements: [],
                blockingMessage: $noPlanMessage,
            );
        }

        if (! PlanClinicalCompleteness::isComplete($plan)) {
            $missing = PlanClinicalCompleteness::missingBenefitLabels($plan);

            return new ClinicalEntitlementSnapshot(
                hasPlan: true,
                isComplete: false,
                missingBenefitLabels: $missing,
                entitlements: [],
                blockingMessage: 'Este plan todavía no tiene configurado el uso clínico. Negocios debe completar el mapa beneficio → servicio → cupo en Planes → Uso clínico. Mientras tanto no se listan servicios tipo 1 ni tildes clínicas del plan.',
            );
        }

        if ($plan->relationLoaded('clinicalSettings')) {
            $settings = $plan->clinicalSettings->filter(
                static fn (PlanBenefitClinicalSetting $row): bool => (bool) $row->applies_clinically
            );
            $settings->loadMissing(['benefit:id,description', 'telemedicineServiceList:id,name']);
        } else {
            $settings = PlanBenefitClinicalSetting::query()
                ->where('plan_id', $plan->id)
                ->where('applies_clinically', true)
                ->with(['benefit:id,description', 'telemedicineServiceList:id,name'])
                ->get();
        }

        $usages = self::loadUsages($telemedicinePatientId, $nroIdentificacion);
        $entitlements = [];

        foreach ($settings as $setting) {
            $entitlements[] = self::entitlementFromSetting($effectDate, $setting, $usages);
        }

        return new ClinicalEntitlementSnapshot(
            hasPlan: true,
            isComplete: true,
            missingBenefitLabels: [],
            entitlements: $entitlements,
            blockingMessage: '',
        );
    }

    /**
     * @param  Collection<int, AffiliateClinicalServiceUsage>  $usages
     */
    private static function entitlementFromSetting(
        CarbonImmutable $effectDate,
        PlanBenefitClinicalSetting $setting,
        Collection $usages,
    ): ClinicalEntitlement {
        $channel = $setting->channel instanceof ClinicalServiceChannel
            ? $setting->channel
            : ClinicalServiceChannel::Type1;
        $scope = $setting->quota_scope instanceof ClinicalQuotaScope
            ? $setting->quota_scope
            : ClinicalQuotaScope::Unlimited;

        $used = 0;
        if ($scope !== ClinicalQuotaScope::Unlimited) {
            $window = ClinicalEntitlementWindow::forEffectDate($effectDate, $scope);
            $relevant = $usages->filter(static function (AffiliateClinicalServiceUsage $usage) use ($channel, $setting, $window): bool {
                if ($usage->channel !== $channel) {
                    return false;
                }

                if ($channel === ClinicalServiceChannel::Type1
                    && (int) $usage->telemedicine_service_list_id !== (int) $setting->telemedicine_service_list_id) {
                    return false;
                }

                if ($window === null) {
                    return true;
                }

                $at = $usage->created_at;
                if ($at === null) {
                    return false;
                }

                return $at->greaterThanOrEqualTo($window['starts_at'])
                    && $at->lessThan($window['ends_at']);
            });

            $used = $scope === ClinicalQuotaScope::DistinctCases
                ? $relevant->pluck('telemedicine_case_id')->filter()->unique()->count()
                : $relevant->count();
        }

        $quota = $scope->requiresQuota() ? (int) $setting->quota : null;
        $remaining = $quota === null ? null : max(0, $quota - $used);

        return new ClinicalEntitlement(
            benefitId: (int) $setting->benefit_id,
            benefitLabel: (string) ($setting->benefit?->description ?? 'Beneficio #'.$setting->benefit_id),
            channel: $channel,
            telemedicineServiceListId: $setting->telemedicine_service_list_id !== null
                ? (int) $setting->telemedicine_service_list_id
                : null,
            telemedicineServiceListName: $setting->telemedicineServiceList?->name,
            quotaScope: $scope,
            quota: $quota,
            used: $used,
            remaining: $remaining,
            exhausted: $quota !== null && $used >= $quota,
        );
    }

    /**
     * @return Collection<int, AffiliateClinicalServiceUsage>
     */
    private static function loadUsages(?int $telemedicinePatientId, string $nroIdentificacion): Collection
    {
        if (($telemedicinePatientId === null || $telemedicinePatientId <= 0) && $nroIdentificacion === '') {
            return collect();
        }

        return AffiliateClinicalServiceUsage::query()
            ->where('status', AffiliateClinicalServiceUsage::STATUS_CONSUMED)
            ->where(function ($query) use ($telemedicinePatientId, $nroIdentificacion): void {
                if ($telemedicinePatientId !== null && $telemedicinePatientId > 0) {
                    $query->where('telemedicine_patient_id', $telemedicinePatientId);
                }

                if ($nroIdentificacion !== '') {
                    $query->orWhere('nro_identificacion', $nroIdentificacion);
                }
            })
            ->get([
                'id',
                'channel',
                'telemedicine_service_list_id',
                'telemedicine_case_id',
                'created_at',
            ]);
    }
}
