<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Models\AffiliateClinicalServiceUsage;
use App\Models\ClinicalServiceOverrideChallenge;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ClinicalUsageLedger
{
    /**
     * @param  array{
     *     channel: ClinicalServiceChannel,
     *     telemedicine_service_list_id?: int|null,
     *     telemedicine_case_id?: int|null,
     *     telemedicine_consultation_patient_id?: int|null,
     *     override_challenge?: ClinicalServiceOverrideChallenge|null
     * }  $input
     */
    public static function consume(TelemedicinePatient $patient, array $input): AffiliateClinicalServiceUsage
    {
        $channel = $input['channel'];
        $serviceListId = isset($input['telemedicine_service_list_id'])
            ? (int) $input['telemedicine_service_list_id']
            : null;
        $challenge = $input['override_challenge'] ?? null;

        return DB::transaction(function () use ($patient, $channel, $serviceListId, $input, $challenge): AffiliateClinicalServiceUsage {
            TelemedicinePatient::query()->whereKey($patient->id)->lockForUpdate()->first();

            $fresh = TelemedicinePatient::query()->findOrFail($patient->id);
            $snapshot = AffiliateClinicalEntitlementResolver::forPatient($fresh);

            if (! $snapshot->isComplete) {
                throw ClinicalEntitlementException::planIncomplete($snapshot->blockingMessage);
            }

            $entitlement = $channel === ClinicalServiceChannel::Type1
                ? $snapshot->forType1($serviceListId)
                : $snapshot->forChannel($channel);

            if ($entitlement === null) {
                throw ClinicalEntitlementException::unauthorized(
                    'Ese servicio no está incluido en el uso clínico de este plan.'
                );
            }

            $caseId = isset($input['telemedicine_case_id']) ? (int) $input['telemedicine_case_id'] : null;
            $countsAsNewUnit = self::shouldConsumeForScope($entitlement, $fresh, $caseId, $serviceListId);

            if (! $countsAsNewUnit) {
                $existing = AffiliateClinicalServiceUsage::query()
                    ->where('status', AffiliateClinicalServiceUsage::STATUS_CONSUMED)
                    ->where('telemedicine_case_id', $caseId)
                    ->where('channel', $channel->value)
                    ->when(
                        $channel === ClinicalServiceChannel::Type1 && $serviceListId !== null,
                        static fn ($query) => $query->where('telemedicine_service_list_id', $serviceListId),
                    )
                    ->where(function ($query) use ($fresh): void {
                        $query->where('telemedicine_patient_id', $fresh->id);
                        $ci = trim((string) $fresh->nro_identificacion);
                        if ($ci !== '') {
                            $query->orWhere('nro_identificacion', $ci);
                        }
                    })
                    ->latest('id')
                    ->first();

                if ($existing instanceof AffiliateClinicalServiceUsage) {
                    return $existing;
                }
            }

            if ($entitlement->exhausted && $countsAsNewUnit && ! $challenge instanceof ClinicalServiceOverrideChallenge) {
                throw ClinicalEntitlementException::overrideRequired($entitlement->helperText());
            }

            if ($entitlement->exhausted && $challenge instanceof ClinicalServiceOverrideChallenge) {
                ClinicalServiceOverrideOtp::assertValidFor(
                    $challenge,
                    $fresh,
                    $entitlement,
                    (int) Auth::id(),
                );
            }

            $scope = $entitlement->quotaScope;
            $window = ClinicalEntitlementWindow::forPatient($fresh, $scope);

            $usage = AffiliateClinicalServiceUsage::query()->create([
                'telemedicine_patient_id' => $fresh->id,
                'nro_identificacion' => $fresh->nro_identificacion,
                'plan_id' => $fresh->plan_id,
                'affiliation_id' => $fresh->afilliation_id,
                'affiliation_corporate_id' => $fresh->afilliation_corporate_id,
                'benefit_id' => $entitlement->benefitId,
                'channel' => $channel->value,
                'telemedicine_service_list_id' => $entitlement->telemedicineServiceListId,
                'telemedicine_case_id' => $input['telemedicine_case_id'] ?? null,
                'telemedicine_consultation_patient_id' => $input['telemedicine_consultation_patient_id'] ?? null,
                'status' => AffiliateClinicalServiceUsage::STATUS_CONSUMED,
                'is_override' => $challenge instanceof ClinicalServiceOverrideChallenge,
                'override_challenge_id' => $challenge instanceof ClinicalServiceOverrideChallenge ? $challenge->id : null,
                'override_reason' => $challenge instanceof ClinicalServiceOverrideChallenge ? $challenge->reason : null,
                'window_starts_at' => $window['starts_at'] ?? null,
                'window_ends_at' => $window['ends_at'] ?? null,
                'created_by' => Auth::user()?->name,
            ]);

            if ($challenge instanceof ClinicalServiceOverrideChallenge) {
                ClinicalServiceOverrideOtp::markConsumed($challenge);
            }

            AffiliateClinicalEntitlementResolver::flush((int) $fresh->id);

            return $usage;
        });
    }

    public static function reverseForConsultation(TelemedicineConsultationPatient $consultation): void
    {
        AffiliateClinicalServiceUsage::query()
            ->where('telemedicine_consultation_patient_id', $consultation->id)
            ->where('status', AffiliateClinicalServiceUsage::STATUS_CONSUMED)
            ->update(['status' => AffiliateClinicalServiceUsage::STATUS_REVERSED]);

        AffiliateClinicalEntitlementResolver::flush();
    }

    public static function reverseForCase(int $caseId): void
    {
        AffiliateClinicalServiceUsage::query()
            ->where('telemedicine_case_id', $caseId)
            ->where('status', AffiliateClinicalServiceUsage::STATUS_CONSUMED)
            ->update(['status' => AffiliateClinicalServiceUsage::STATUS_REVERSED]);

        AffiliateClinicalEntitlementResolver::flush();
    }

    /**
     * Distinct-cases: a second assignment in the same case does not consume
     * another unit. Returns true when this case already has a consumed usage.
     */
    public static function caseAlreadyCounts(
        TelemedicinePatient $patient,
        ClinicalServiceChannel $channel,
        ?int $caseId,
        ?int $serviceListId = null,
    ): bool {
        if ($caseId === null) {
            return false;
        }

        return AffiliateClinicalServiceUsage::query()
            ->where('status', AffiliateClinicalServiceUsage::STATUS_CONSUMED)
            ->where('telemedicine_case_id', $caseId)
            ->where('channel', $channel->value)
            ->when(
                $channel === ClinicalServiceChannel::Type1 && $serviceListId !== null,
                static fn ($query) => $query->where('telemedicine_service_list_id', $serviceListId),
            )
            ->where(function ($query) use ($patient): void {
                $query->where('telemedicine_patient_id', $patient->id);
                $ci = trim((string) $patient->nro_identificacion);
                if ($ci !== '') {
                    $query->orWhere('nro_identificacion', $ci);
                }
            })
            ->exists();
    }

    public static function shouldConsumeForScope(
        ClinicalEntitlement $entitlement,
        TelemedicinePatient $patient,
        ?int $caseId,
        ?int $serviceListId = null,
    ): bool {
        if ($entitlement->quotaScope !== ClinicalQuotaScope::DistinctCases) {
            return true;
        }

        return ! self::caseAlreadyCounts($patient, $entitlement->channel, $caseId, $serviceListId);
    }
}
