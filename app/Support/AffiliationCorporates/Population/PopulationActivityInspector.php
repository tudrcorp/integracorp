<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Models\AffiliateClinicalServiceUsage;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Actividad clínica u operativa de afiliados corporativos, en lote.
 *
 * - Abierta (bloquea una baja): caso de telemedicina sin ALTA MEDICA, coordinación
 *   de Operaciones no FINALIZADA u orden de servicio EN GESTION. Los casos
 *   eliminados no cuentan (scope global de TelemedicineCase).
 * - Posterior a una fecha (bloquea revertir un alta): caso, coordinación o uso
 *   de cupo clínico creados después de aplicar la carga.
 *
 * El paciente se liga al afiliado como lo hace el sistema: misma afiliación
 * corporativa (vigente en el paciente) y misma cédula.
 */
final class PopulationActivityInspector
{
    /**
     * Pacientes de telemedicina ligados a cada afiliado.
     *
     * @param  iterable<AffiliateCorporate>  $affiliates
     * @return array<int, list<TelemedicinePatient>>
     */
    public static function linkedPatients(AffiliationCorporate $owner, iterable $affiliates): array
    {
        $patients = TelemedicinePatient::query()
            ->where('afilliation_corporate_id', $owner->getKey())
            ->get(['id', 'nro_identificacion', 'code_affiliation', 'type_affiliation', 'status_affiliation', 'full_name']);

        $byDocument = [];
        foreach ($patients as $patient) {
            if (strtoupper((string) $patient->type_affiliation) !== 'CORPORATIVO') {
                continue;
            }

            if (filled($owner->code) && filled($patient->code_affiliation) && (string) $patient->code_affiliation !== (string) $owner->code) {
                continue;
            }

            $key = PopulationPerson::documentKey((string) $patient->nro_identificacion);
            if ($key !== '') {
                $byDocument[$key][] = $patient;
            }
        }

        $out = [];
        foreach ($affiliates as $affiliate) {
            $key = PopulationPerson::documentKey((string) $affiliate->nro_identificacion);
            $out[(int) $affiliate->getKey()] = $key !== '' ? ($byDocument[$key] ?? []) : [];
        }

        return $out;
    }

    /**
     * Actividad abierta por afiliado (solo los que tienen alguna).
     *
     * @param  array<int, list<TelemedicinePatient>>  $linkedPatients
     * @return array<int, list<string>>
     */
    public static function openActivity(array $linkedPatients): array
    {
        $patientToAffiliate = self::patientIndex($linkedPatients);
        if ($patientToAffiliate === []) {
            return [];
        }

        $patientIds = array_keys($patientToAffiliate);
        $found = [];

        TelemedicineCase::query()
            ->whereIn('telemedicine_patient_id', $patientIds)
            ->where('status', '!=', 'ALTA MEDICA')
            ->get(['id', 'code', 'status', 'telemedicine_patient_id'])
            ->each(function (TelemedicineCase $case) use (&$found, $patientToAffiliate): void {
                foreach ($patientToAffiliate[(int) $case->telemedicine_patient_id] ?? [] as $affiliateId) {
                    $found[$affiliateId][] = 'Caso de telemedicina '.($case->code ?: '#'.$case->id).' en '.$case->status;
                }
            });

        $coordinations = OperationCoordinationService::query()
            ->whereIn('telemedicine_patient_id', $patientIds)
            ->get(['id', 'status', 'telemedicine_patient_id']);

        foreach ($coordinations as $coordination) {
            if (strtoupper((string) $coordination->status) === 'FINALIZADO') {
                continue;
            }

            foreach ($patientToAffiliate[(int) $coordination->telemedicine_patient_id] ?? [] as $affiliateId) {
                $found[$affiliateId][] = 'Coordinación de Operaciones #'.$coordination->id.' en '.$coordination->status;
            }
        }

        $coordinationPatient = $coordinations->pluck('telemedicine_patient_id', 'id')->all();

        if ($coordinationPatient !== []) {
            OperationServiceOrder::query()
                ->whereIn('operation_coordination_service_id', array_keys($coordinationPatient))
                ->where('status', 'EN GESTION')
                ->get(['id', 'status', 'operation_coordination_service_id'])
                ->each(function (OperationServiceOrder $order) use (&$found, $patientToAffiliate, $coordinationPatient): void {
                    $patientId = (int) ($coordinationPatient[(int) $order->operation_coordination_service_id] ?? 0);
                    foreach ($patientToAffiliate[$patientId] ?? [] as $affiliateId) {
                        $found[$affiliateId][] = 'Orden de servicio #'.$order->id.' EN GESTION';
                    }
                });
        }

        return array_map(static fn (array $reasons): array => array_values(array_unique($reasons)), $found);
    }

    /**
     * Actividad creada después de una fecha, por afiliado.
     *
     * @param  array<int, list<TelemedicinePatient>>  $linkedPatients
     * @return array<int, list<string>>
     */
    public static function activitySince(array $linkedPatients, CarbonInterface $since): array
    {
        $patientToAffiliate = self::patientIndex($linkedPatients);
        if ($patientToAffiliate === []) {
            return [];
        }

        $patientIds = array_keys($patientToAffiliate);
        $found = [];
        $add = static function (int $patientId, string $reason) use (&$found, $patientToAffiliate): void {
            foreach ($patientToAffiliate[$patientId] ?? [] as $affiliateId) {
                $found[$affiliateId][] = $reason;
            }
        };

        TelemedicineCase::query()->whereIn('telemedicine_patient_id', $patientIds)->where('created_at', '>', $since)
            ->get(['id', 'code', 'telemedicine_patient_id'])
            ->each(fn (TelemedicineCase $case) => $add((int) $case->telemedicine_patient_id, 'Caso de telemedicina '.($case->code ?: '#'.$case->id).' abierto después de aplicar la carga'));

        OperationCoordinationService::query()->whereIn('telemedicine_patient_id', $patientIds)->where('created_at', '>', $since)
            ->get(['id', 'telemedicine_patient_id'])
            ->each(fn (OperationCoordinationService $row) => $add((int) $row->telemedicine_patient_id, 'Coordinación de Operaciones #'.$row->id.' creada después de aplicar la carga'));

        AffiliateClinicalServiceUsage::query()->whereIn('telemedicine_patient_id', $patientIds)->where('created_at', '>', $since)
            ->get(['id', 'telemedicine_patient_id'])
            ->each(fn (AffiliateClinicalServiceUsage $row) => $add((int) $row->telemedicine_patient_id, 'Consumió cupo clínico después de aplicar la carga'));

        return array_map(static fn (array $reasons): array => array_values(array_unique($reasons)), $found);
    }

    /**
     * @param  array<int, list<TelemedicinePatient>>  $linkedPatients
     * @return array<int, list<int>>
     */
    private static function patientIndex(array $linkedPatients): array
    {
        $index = [];
        foreach ($linkedPatients as $affiliateId => $patients) {
            foreach ($patients as $patient) {
                $index[(int) $patient->getKey()][] = (int) $affiliateId;
            }
        }

        return $index;
    }

    /**
     * @param  Collection<int, AffiliateCorporate>  $affiliates
     * @return array<int, list<string>>
     */
    public static function openActivityFor(AffiliationCorporate $owner, Collection $affiliates): array
    {
        return self::openActivity(self::linkedPatients($owner, $affiliates));
    }
}
