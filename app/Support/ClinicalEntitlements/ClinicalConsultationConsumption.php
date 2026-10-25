<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalServiceChannel;
use App\Models\ClinicalServiceOverrideChallenge;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicineServiceList;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class ClinicalConsultationConsumption
{
    /**
     * @param  array<string, mixed>  $formData
     * @param  array<string, ClinicalServiceOverrideChallenge>  $overrides  keyed by channel value
     */
    public static function assertCanSave(
        TelemedicinePatient $patient,
        array $formData,
        ?int $caseId,
        array $overrides = [],
    ): void {
        $snapshot = AffiliateClinicalEntitlementResolver::forPatient($patient);

        if (! $snapshot->hasPlan) {
            return;
        }

        if (! $snapshot->isComplete) {
            throw ValidationException::withMessages([
                'telemedicine_service_list_id' => $snapshot->blockingMessage,
            ]);
        }

        $channels = self::requestedChannels($formData);

        foreach ($channels as $channel => $serviceListId) {
            $entitlement = $channel === ClinicalServiceChannel::Type1->value
                ? $snapshot->forType1($serviceListId)
                : $snapshot->forChannel(ClinicalServiceChannel::from($channel));

            if ($entitlement === null) {
                if (self::unmappedChannelMayProceedWithoutQuota($channel)
                    || ($channel === ClinicalServiceChannel::Type1->value && TelemedicineServiceList::isStandardType1($serviceListId))) {
                    continue;
                }

                throw ValidationException::withMessages([
                    'telemedicine_service_list_id' => 'Ese servicio no está incluido en el uso clínico de este plan.',
                ]);
            }

            $countsNew = ClinicalUsageLedger::shouldConsumeForScope(
                $entitlement,
                $patient,
                $caseId,
                $serviceListId,
            );

            if ($entitlement->exhausted && $countsNew && ! $entitlement->channel->requiresOverrideWhenExhausted()) {
                /*
                 * Sin OTP fuera de Servicios Macro: el formulario oculta lo cubierto
                 * con el cupo agotado. Si aun así llega, es estado arrastrado o una
                 * petición manipulada, y no se guarda.
                 */
                throw ValidationException::withMessages([
                    'complements' => $entitlement->helperText(),
                ]);
            }

            if ($entitlement->exhausted && $countsNew) {
                $override = $overrides[$channel] ?? null;
                if (! $override instanceof ClinicalServiceOverrideChallenge) {
                    throw ValidationException::withMessages([
                        'telemedicine_service_list_id' => $entitlement->helperText().' Confirme si desea continuar y solicite la clave OTP.',
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $formData
     * @param  array<string, ClinicalServiceOverrideChallenge>  $overrides
     */
    public static function record(
        TelemedicineConsultationPatient $consultation,
        TelemedicinePatient $patient,
        array $formData,
        array $overrides = [],
    ): void {
        $snapshot = AffiliateClinicalEntitlementResolver::forPatient($patient);

        if (! $snapshot->hasPlan || ! $snapshot->isComplete) {
            return;
        }

        $channels = self::requestedChannels($formData);

        foreach ($channels as $channelValue => $serviceListId) {
            $channel = ClinicalServiceChannel::from($channelValue);
            $entitlement = $channel === ClinicalServiceChannel::Type1
                ? $snapshot->forType1($serviceListId)
                : $snapshot->forChannel($channel);

            if ($entitlement === null) {
                continue;
            }

            ClinicalUsageLedger::consume($patient, [
                'channel' => $channel,
                'telemedicine_service_list_id' => $serviceListId,
                'telemedicine_case_id' => $consultation->telemedicine_case_id,
                'telemedicine_consultation_patient_id' => $consultation->id,
                'override_challenge' => $overrides[$channelValue] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, int|null>
     */
    public static function requestedChannels(array $formData): array
    {
        $out = [];

        if (filled($formData['telemedicine_service_list_id'] ?? null)) {
            $out[ClinicalServiceChannel::Type1->value] = (int) $formData['telemedicine_service_list_id'];
        }

        /*
         * Sólo lo cubierto consume cupo. Los campos «otros» (`other_labs`,
         * `other_studies`, `other_specialist`) y los medicamentos escritos en
         * «No cubierto» son servicios que el plan no cubre: se registran, pero
         * no descuentan ni bloquean el beneficio.
         */
        $complements = array_map('intval', (array) ($formData['complements'] ?? []));
        $hasLabs = $complements !== [] && in_array(2, $complements, true)
            && filled($formData['labs'] ?? null);
        $hasImaging = $complements !== [] && in_array(2, $complements, true)
            && filled($formData['studies'] ?? null);
        $hasMeds = in_array(1, $complements, true)
            && self::hasCoveredMedication($formData['medications'] ?? null);
        $hasSpecialist = in_array(3, $complements, true)
            && filled($formData['consult_specialist'] ?? null);

        if ($hasMeds) {
            $out[ClinicalServiceChannel::Medication->value] = null;
        }
        if ($hasLabs) {
            $out[ClinicalServiceChannel::Laboratory->value] = null;
        }
        if ($hasImaging) {
            $out[ClinicalServiceChannel::Imaging->value] = null;
        }
        if ($hasSpecialist) {
            $out[ClinicalServiceChannel::Specialist->value] = null;
        }

        return $out;
    }

    /**
     * Un medicamento consume cupo solo si sale del inventario TDC o va como
     * cubierto sin inventario. Uno escrito en «No cubierto» no descuenta.
     */
    public static function hasCoveredMedication(mixed $medications): bool
    {
        if (! is_array($medications)) {
            return false;
        }

        foreach ($medications as $row) {
            if (is_array($row) && TelemedicineMedicationCoverage::rowIsCovered($row)) {
                return true;
            }
        }

        return false;
    }

    public static function unmappedChannelMayProceedWithoutQuota(string $channelValue): bool
    {
        return $channelValue === ClinicalServiceChannel::Specialist->value;
    }

    public static function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
