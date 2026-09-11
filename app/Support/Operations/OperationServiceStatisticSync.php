<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceStatistic;
use App\Models\TelemedicineAmdInform;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineFollowUp;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineInitialDiagnosisUpdater;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class OperationServiceStatisticSync
{
    public const YES = 'SI';

    public const NO = 'NO';

    private const PLACEHOLDERS = ['', '...', '—', '-', 'N/A', 'NA', 'NO ESPECIFICADO'];

    /**
     * @var list<string>
     */
    private const DENIED_STATUS_TOKENS = ['NEGADO', 'NEGADA', 'ANULADO', 'ANULADA', 'REVERSADO', 'REVERSADA', 'RECHAZADO', 'RECHAZADA'];

    private static bool $muted = false;

    public static function syncFromModel(Model $model): void
    {
        if (self::$muted || ! self::tableReady()) {
            return;
        }

        try {
            if ($model instanceof TelemedicinePatientLab
                || $model instanceof TelemedicinePatientMedications
                || $model instanceof TelemedicinePatientStudy
                || $model instanceof TelemedicinePatientSpecialty) {
                self::syncItem($model);

                return;
            }

            if ($model instanceof OperationCoordinationService) {
                self::syncFromCoordination($model);

                return;
            }

            if ($model instanceof OperationServiceOrder) {
                $coordination = $model->operationCoordinationService;
                if ($coordination instanceof OperationCoordinationService) {
                    self::syncFromCoordination($coordination);
                }

                return;
            }

            if ($model instanceof TelemedicineCase) {
                self::syncFromCase($model);

                return;
            }

            $caseId = (int) ($model->getAttribute('telemedicine_case_id') ?? 0);
            if ($caseId > 0 && (
                $model instanceof ObservationCase
                || $model instanceof TelemedicineFollowUp
                || $model instanceof TelemedicineConsultationPatient
            )) {
                self::syncFromCaseId($caseId);
            }
        } catch (Throwable $exception) {
            Log::error('OperationServiceStatisticSync: no se pudo sincronizar el hecho', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public static function forgetFromModel(Model $model): void
    {
        if (self::$muted || ! self::tableReady()) {
            return;
        }

        try {
            if ($model instanceof TelemedicinePatientLab
                || $model instanceof TelemedicinePatientMedications
                || $model instanceof TelemedicinePatientStudy
                || $model instanceof TelemedicinePatientSpecialty) {
                self::forgetSource(self::sourceTypeForItem($model), (int) $model->getKey());

                return;
            }

            if ($model instanceof OperationCoordinationService) {
                self::forgetStandaloneCoordination((int) $model->getKey());
                self::syncFromCoordinationId((int) $model->getKey());

                return;
            }

            if ($model instanceof TelemedicineCase) {
                return;
            }

            $caseId = (int) ($model->getAttribute('telemedicine_case_id') ?? 0);
            if ($caseId > 0) {
                self::syncFromCaseId($caseId);
            }
        } catch (Throwable $exception) {
            Log::error('OperationServiceStatisticSync: no se pudo olvidar el hecho', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public static function markCaseDenied(TelemedicineCase $case): void
    {
        if (! self::tableReady()) {
            return;
        }

        $caseId = (int) $case->getKey();
        if ($caseId < 1) {
            return;
        }

        OperationServiceStatistic::query()
            ->where('telemedicine_case_id', $caseId)
            ->update([
                'case_denied' => self::YES,
                'case_status' => self::filledText($case->status) ?? 'REVERSADO',
                'case_last_touched_by' => self::resolvePersonName(Auth::user()?->name ?? Auth::id()) ?? 'SISTEMA',
                'updated_at' => now(),
            ]);
    }

    public static function syncFromCaseId(int $caseId): void
    {
        if ($caseId < 1 || ! self::tableReady()) {
            return;
        }

        $case = TelemedicineCase::query()->find($caseId);
        if (! $case instanceof TelemedicineCase) {
            return;
        }

        self::syncFromCase($case);
    }

    public static function syncFromCase(TelemedicineCase $case): void
    {
        if (! self::tableReady()) {
            return;
        }

        $caseId = (int) $case->getKey();
        if ($caseId < 1) {
            return;
        }

        $items = self::itemsForCase($caseId);

        foreach ($items as $item) {
            try {
                self::syncItem($item);
            } catch (Throwable $exception) {
                Log::error('OperationServiceStatisticSync: no se pudo sincronizar el ítem del caso', [
                    'telemedicine_case_id' => $caseId,
                    'item' => $item::class,
                    'id' => $item->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $itemCoordinationIds = $items
            ->map(fn (Model $item): int => (int) ($item->getAttribute('operation_coordination_service_id') ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        OperationCoordinationService::query()
            ->where('telemedicine_case_id', $caseId)
            ->orderBy('id')
            ->each(function (OperationCoordinationService $coordination) use ($itemCoordinationIds): void {
                if ($itemCoordinationIds->contains((int) $coordination->id)) {
                    return;
                }

                try {
                    self::syncStandaloneCoordination($coordination);
                } catch (Throwable $exception) {
                    Log::error('OperationServiceStatisticSync: no se pudo sincronizar la coordinación standalone', [
                        'operation_coordination_service_id' => $coordination->id,
                        'telemedicine_case_id' => $coordination->telemedicine_case_id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });
    }

    public static function syncFromCoordinationId(int $coordinationId): void
    {
        if ($coordinationId < 1 || ! self::tableReady()) {
            return;
        }

        $coordination = OperationCoordinationService::query()->find($coordinationId);
        if (! $coordination instanceof OperationCoordinationService) {
            return;
        }

        self::syncFromCoordination($coordination);
    }

    public static function syncFromCoordination(OperationCoordinationService $coordination): void
    {
        if (! self::tableReady()) {
            return;
        }

        $items = self::itemsForCoordination((int) $coordination->id);

        foreach ($items as $item) {
            self::syncItem($item);
        }

        if ($items->isEmpty()) {
            self::syncStandaloneCoordination($coordination);

            return;
        }

        self::forgetStandaloneCoordination((int) $coordination->id);
    }

    public static function syncItem(Model $item): void
    {
        if (! self::tableReady() || $item->getKey() === null) {
            return;
        }

        $payload = self::payloadFromItem($item);
        if ($payload === []) {
            return;
        }

        $coordinationId = (int) ($item->getAttribute('operation_coordination_service_id') ?? 0);
        if ($coordinationId > 0) {
            self::forgetStandaloneCoordination($coordinationId);
        }

        self::persist($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadFromItem(Model $item): array
    {
        $sourceType = self::sourceTypeForItem($item);
        $context = self::itemContext($item, $sourceType);

        return self::assembleSnapshot($context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function assembleSnapshot(array $context): array
    {
        $discountPercent = self::nullableDecimal($context['discount_percent'] ?? null);
        $discountAmount = self::nullableDecimal($context['discount_amount'] ?? null);
        $farmadocDetail = self::filledText($context['farmadoc_detail'] ?? null);
        $qcDescription = self::filledText($context['qc_description'] ?? null);
        $coverage = self::filledText($context['coverage'] ?? null);

        return [
            'telemedicine_case_id' => self::nullableId($context['telemedicine_case_id'] ?? null),
            'telemedicine_consultation_patient_id' => self::nullableId($context['telemedicine_consultation_patient_id'] ?? null),
            'operation_coordination_service_id' => self::nullableId($context['operation_coordination_service_id'] ?? null),
            'operation_service_order_id' => self::nullableId($context['operation_service_order_id'] ?? null),
            'source_type' => (string) ($context['source_type'] ?? ''),
            'source_id' => (int) ($context['source_id'] ?? 0),
            'started_on' => $context['started_on'] ?? null,
            'started_at_time' => $context['started_at_time'] ?? null,
            'service_on' => $context['service_on'] ?? null,
            'business_line' => self::filledText($context['business_line'] ?? null),
            'case_code' => self::filledText($context['case_code'] ?? null),
            'case_status' => self::filledText($context['case_status'] ?? null),
            'service_status' => self::filledText($context['service_status'] ?? null),
            'case_created_by' => self::filledText($context['case_created_by'] ?? null),
            'case_last_touched_by' => self::filledText($context['case_last_touched_by'] ?? null),
            'plan_holder_name' => self::filledText($context['plan_holder_name'] ?? null),
            'plan_holder_document' => self::filledText($context['plan_holder_document'] ?? null),
            'patient_name' => self::filledText($context['patient_name'] ?? null),
            'patient_document' => self::filledText($context['patient_document'] ?? null),
            'patient_birth_date' => $context['patient_birth_date'] ?? null,
            'patient_relationship' => self::filledText($context['patient_relationship'] ?? null),
            'patient_age' => self::nullableInt($context['patient_age'] ?? null),
            'contractor' => self::filledText($context['contractor'] ?? null),
            'agency_name' => self::filledText($context['agency_name'] ?? null),
            'agent_name' => self::filledText($context['agent_name'] ?? null),
            'region' => self::filledText($context['region'] ?? null),
            'state' => self::filledText($context['state'] ?? null),
            'city' => self::filledText($context['city'] ?? null),
            'address' => self::filledText($context['address'] ?? null),
            'patient_phone' => self::filledText($context['patient_phone'] ?? null),
            'patient_email' => self::filledText($context['patient_email'] ?? null),
            'consultation_reason' => self::filledText($context['consultation_reason'] ?? null),
            'initial_diagnosis' => self::filledText($context['initial_diagnosis'] ?? null),
            'final_diagnosis' => self::filledText($context['final_diagnosis'] ?? null),
            'service' => self::filledText($context['service'] ?? null),
            'specific_service' => self::filledText($context['specific_service'] ?? null),
            'service_type' => self::filledText($context['service_type'] ?? null),
            'coverage' => $coverage,
            'management_provider' => self::filledText($context['management_provider'] ?? null),
            'service_provider' => self::filledText($context['service_provider'] ?? null),
            'medical_provider' => self::filledText($context['medical_provider'] ?? null),
            'farmadoc_derived' => $farmadocDetail !== null ? self::YES : self::NO,
            'farmadoc_detail' => $farmadocDetail,
            'negotiation_type' => self::filledText($context['negotiation_type'] ?? null),
            'negotiation_status' => self::filledText($context['negotiation_status'] ?? null),
            'net_price' => self::nullableDecimal($context['net_price'] ?? null),
            'tdec_profit_percent' => self::nullableDecimal($context['tdec_profit_percent'] ?? null),
            'quoted_amount' => self::nullableDecimal($context['quoted_amount'] ?? null),
            'discount_negotiation' => self::yesNoFromFlag($context['discount_negotiation'] ?? null, $discountPercent, $discountAmount),
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'quote_number' => self::filledText($context['quote_number'] ?? null),
            'approval_number' => self::filledText($context['approval_number'] ?? null),
            'service_order_number' => self::filledText($context['service_order_number'] ?? null),
            'invoice_number' => self::filledText($context['invoice_number'] ?? null),
            'invoiced_amount' => self::nullableDecimal($context['invoiced_amount'] ?? null),
            'invoice_issued_on' => $context['invoice_issued_on'] ?? null,
            'incidence' => self::filledText($context['incidence'] ?? null),
            'case_denied' => self::caseDeniedLabel($context['case_status'] ?? null, $context['case_denied'] ?? null),
            'qc_received' => $qcDescription !== null ? self::YES : self::NO,
            'observations' => self::filledText($context['observations'] ?? null),
        ];
    }

    public static function coverageLabel(Model $item, string $sourceType): string
    {
        $covered = self::itemIsCovered($item, $sourceType);

        return match ($covered) {
            true => 'Cubierto',
            false => 'No cubierto',
            default => 'Sin dato',
        };
    }

    public static function sourceTypeForItem(Model $item): string
    {
        return match (true) {
            $item instanceof TelemedicinePatientLab => OperationServiceStatistic::SOURCE_LAB,
            $item instanceof TelemedicinePatientMedications => OperationServiceStatistic::SOURCE_MEDICATION,
            $item instanceof TelemedicinePatientStudy => OperationServiceStatistic::SOURCE_STUDY,
            $item instanceof TelemedicinePatientSpecialty => OperationServiceStatistic::SOURCE_SPECIALTY,
            default => 'unknown',
        };
    }

    public static function standaloneSourceType(?string $specificService): string
    {
        $normalized = mb_strtoupper(trim((string) $specificService));

        return match ($normalized) {
            'TRASLADO EN AMBULANCIA' => OperationServiceStatistic::SOURCE_AMBULANCE,
            'INGRESO A CLINICA' => OperationServiceStatistic::SOURCE_CLINIC_ADMISSION,
            default => OperationServiceStatistic::SOURCE_TPA_RETAIL,
        };
    }

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('operation_service_statistics');
        } catch (Throwable) {
            return false;
        }
    }

    private static function syncStandaloneCoordination(OperationCoordinationService $coordination): void
    {
        if ($coordination->getKey() === null) {
            return;
        }

        $payload = self::payloadFromCoordination($coordination);
        if ($payload === []) {
            return;
        }

        self::persist($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadFromCoordination(OperationCoordinationService $coordination): array
    {
        $context = self::coordinationContext($coordination);

        return self::assembleSnapshot($context);
    }

    /**
     * @return array<string, mixed>
     */
    private static function itemContext(Model $item, string $sourceType): array
    {
        $coordination = self::coordinationForItem($item);
        $case = self::caseFor($item, $coordination);
        $shared = self::sharedContext($case, $coordination, $item);

        $itemName = self::itemName($item, $sourceType);
        $channel = self::channelForSource($sourceType, $coordination);
        $order = self::resolveServiceOrder($coordination, $itemName, $channel);

        return array_merge($shared, [
            'source_type' => $sourceType,
            'source_id' => (int) $item->getKey(),
            'telemedicine_consultation_patient_id' => $item->getAttribute('telemedicine_consultation_patient_id'),
            'specific_service' => $itemName,
            'service' => $channel,
            'service_type' => self::filledText($coordination?->type_service) ?? self::filledText($coordination?->specific_service) ?? $channel,
            'coverage' => self::coverageLabel($item, $sourceType),
            'operation_service_order_id' => $order?->id,
            'service_order_number' => self::filledText($order?->order_number) ?? self::filledText($coordination?->service_order_number),
            'invoice_number' => self::filledText($order?->invoice_number) ?? self::filledText($coordination?->bill_number),
            'invoiced_amount' => $order?->invoice_amount_usd ?? $coordination?->bill_price,
            'invoice_issued_on' => self::toDateString($order?->invoice_date ?? $coordination?->bill_date),
            'medical_provider' => self::medicalProviderName($coordination, $order, $case),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function coordinationContext(OperationCoordinationService $coordination): array
    {
        $case = self::caseFor(null, $coordination);
        $shared = self::sharedContext($case, $coordination, $coordination);
        $sourceType = self::standaloneSourceType($coordination->specific_service);
        $order = $coordination->relationLoaded('operationServiceOrders')
            ? $coordination->operationServiceOrders->sortByDesc('id')->first()
            : $coordination->operationServiceOrders()->latest('id')->first();

        return array_merge($shared, [
            'source_type' => $sourceType,
            'source_id' => (int) $coordination->id,
            'telemedicine_consultation_patient_id' => $coordination->telemedicine_consultation_patient_id,
            'specific_service' => self::filledText($coordination->specific_service) ?? self::filledText($coordination->servicie),
            'service' => self::filledText($coordination->servicie) ?? self::filledText($coordination->general_service) ?? self::filledText($coordination->specific_service),
            'service_type' => self::filledText($coordination->type_service) ?? self::filledText($coordination->specific_service),
            'coverage' => RegisterTpaRetailServicesAction::isStandaloneSpecificService($coordination->specific_service)
                ? 'No cubierto'
                : 'Sin dato',
            'operation_service_order_id' => $order?->id,
            'service_order_number' => self::filledText($order?->order_number) ?? self::filledText($coordination->service_order_number),
            'invoice_number' => self::filledText($order?->invoice_number) ?? self::filledText($coordination->bill_number),
            'invoiced_amount' => $order?->invoice_amount_usd ?? $coordination->bill_price,
            'invoice_issued_on' => self::toDateString($order?->invoice_date ?? $coordination->bill_date),
            'medical_provider' => self::medicalProviderName($coordination, $order, $case),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function sharedContext(?TelemedicineCase $case, ?OperationCoordinationService $coordination, Model $source): array
    {
        $patient = self::patientFor($case, $coordination, $source);
        if ($patient instanceof TelemedicinePatient && $patient->exists) {
            $patient->loadMissing([
                'businessLine',
                'state.region',
                'city',
                'afilliation.agency',
                'afilliation.agent',
                'afilliationCorporate.agency',
                'afilliationCorporate.agent',
            ]);
        }
        if ($coordination instanceof OperationCoordinationService && $coordination->exists) {
            $coordination->loadMissing(['businessLine', 'state.region', 'city', 'supplier', 'telemedicineDoctor', 'operationServiceOrders.operationServiceOrderItems', 'operationServiceOrders.doctorNurse']);
        }
        if ($case instanceof TelemedicineCase && $case->exists) {
            $case->loadMissing(['telemedicineDoctor', 'telemedicinePatient']);
        }
        $consultation = self::consultationFor($case, $coordination, $source);
        $started = self::startedDateTime($case, $coordination, $source);
        $commercial = self::commercialSnapshot($patient);

        return array_merge($commercial, [
            'telemedicine_case_id' => $case?->id ?? $source->getAttribute('telemedicine_case_id'),
            'operation_coordination_service_id' => $coordination?->id ?? $source->getAttribute('operation_coordination_service_id'),
            'started_on' => $started?->toDateString(),
            'started_at_time' => $started?->format('H:i:s'),
            'service_on' => self::toDateString($coordination?->date_service ?? $source->getAttribute('created_at')),
            'business_line' => self::businessLineName($patient, $coordination),
            'case_code' => self::filledText($case?->code) ?? self::filledText($coordination?->reference_number),
            'case_status' => self::filledText($case?->status),
            'service_status' => self::filledText($coordination?->status),
            'case_created_by' => self::resolvePersonName($case?->assigned_by),
            'case_last_touched_by' => self::lastTouchedBy($case, $coordination),
            'patient_name' => self::filledText($patient?->full_name) ?? self::filledText($coordination?->patient) ?? self::filledText($case?->patient_name),
            'patient_document' => self::filledText($patient?->nro_identificacion) ?? self::filledText($coordination?->ci_patient),
            'patient_birth_date' => self::toDateString($patient?->birth_date ?? $coordination?->birth_date_patient),
            'patient_relationship' => self::filledText($coordination?->relationship_patient) ?? self::relationshipForPatient($patient),
            'patient_age' => $patient?->age ?? $coordination?->age_patient ?? $case?->patient_age,
            'region' => self::regionName($patient, $coordination),
            'state' => self::geoName($patient?->state ?? $coordination?->state),
            'city' => self::geoName($patient?->city ?? $coordination?->city),
            'address' => self::filledText($patient?->address) ?? self::filledText($coordination?->address) ?? self::filledText($case?->patient_address),
            'patient_phone' => self::filledText($patient?->phone) ?? self::filledText($patient?->phone_contact) ?? self::filledText($coordination?->phone_holder) ?? self::filledText($case?->patient_phone),
            'patient_email' => self::filledText($patient?->email) ?? self::filledText($patient?->email_contact),
            'consultation_reason' => self::filledText($consultation?->reason_consultation) ?? self::filledText($case?->reason),
            'initial_diagnosis' => self::initialDiagnosis($case, $consultation),
            'final_diagnosis' => self::finalDiagnosis($case, $consultation),
            'management_provider' => self::filledText($coordination?->managed_by) ?? self::filledText($case?->managed_by),
            'service_provider' => self::serviceProviderName($coordination),
            'farmadoc_detail' => $coordination?->farmadoc,
            'negotiation_type' => $coordination?->type_negotiation,
            'negotiation_status' => $coordination?->status_negotiation,
            'net_price' => $coordination?->neto,
            'tdec_profit_percent' => $coordination?->porcen_tdec,
            'quoted_amount' => $coordination?->quote_price,
            'discount_negotiation' => $coordination?->negotiation,
            'discount_percent' => $coordination?->porcen_discount,
            'discount_amount' => $coordination?->price_discount,
            'quote_number' => $coordination?->quote_number,
            'approval_number' => $coordination?->approved_number,
            'incidence' => $coordination?->incidence,
            'qc_description' => $coordination?->qc_description,
            'observations' => $coordination?->observations,
        ]);
    }

    /**
     * @return array{plan_holder_name: ?string, plan_holder_document: ?string, contractor: ?string, agency_name: ?string, agent_name: ?string}
     */
    private static function commercialSnapshot(?TelemedicinePatient $patient): array
    {
        $empty = [
            'plan_holder_name' => null,
            'plan_holder_document' => null,
            'contractor' => null,
            'agency_name' => null,
            'agent_name' => null,
        ];

        if (! $patient instanceof TelemedicinePatient) {
            return $empty;
        }

        $affiliation = $patient->afilliation;
        if ($affiliation instanceof Affiliation) {
            $affiliation->loadMissing(['agency', 'agent']);

            return [
                'plan_holder_name' => self::filledText($affiliation->full_name_ti),
                'plan_holder_document' => self::filledText($affiliation->nro_identificacion_ti),
                'contractor' => self::filledText($affiliation->full_name_ti),
                'agency_name' => self::filledText($affiliation->agency?->name_corporative),
                'agent_name' => self::filledText($affiliation->agent?->name),
            ];
        }

        $corporateAffiliation = $patient->afilliationCorporate;
        if ($corporateAffiliation instanceof AffiliationCorporate) {
            $corporateAffiliation->loadMissing(['agency', 'agent']);
            $titular = self::corporateTitular($corporateAffiliation, $patient);

            return [
                'plan_holder_name' => $titular['name'],
                'plan_holder_document' => $titular['document'],
                'contractor' => self::filledText($corporateAffiliation->name_corporate) ?? self::filledText($patient->name_corporate),
                'agency_name' => self::filledText($corporateAffiliation->agency?->name_corporative),
                'agent_name' => self::filledText($corporateAffiliation->agent?->name),
            ];
        }

        return [
            'plan_holder_name' => self::filledText($patient->full_name),
            'plan_holder_document' => self::filledText($patient->nro_identificacion),
            'contractor' => self::filledText($patient->name_corporate),
            'agency_name' => null,
            'agent_name' => null,
        ];
    }

    /**
     * @return array{name: ?string, document: ?string}
     */
    private static function corporateTitular(AffiliationCorporate $affiliation, TelemedicinePatient $patient): array
    {
        $linked = TelemedicinePatientPlanBridge::linkedAffiliateCorporate($patient);
        if ($linked instanceof AffiliateCorporate && self::isTitularRelationship($linked->relationship)) {
            return [
                'name' => trim(trim((string) $linked->first_name).' '.trim((string) $linked->last_name)) ?: null,
                'document' => self::filledText($linked->nro_identificacion),
            ];
        }

        $titular = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliation->id)
            ->whereRaw('UPPER(TRIM(relationship)) = ?', ['TITULAR'])
            ->orderBy('id')
            ->first();

        if ($titular instanceof AffiliateCorporate) {
            return [
                'name' => trim(trim((string) $titular->first_name).' '.trim((string) $titular->last_name)) ?: null,
                'document' => self::filledText($titular->nro_identificacion),
            ];
        }

        return [
            'name' => self::filledText($patient->full_name),
            'document' => self::filledText($patient->nro_identificacion),
        ];
    }

    private static function persist(array $payload): void
    {
        $sourceType = (string) ($payload['source_type'] ?? '');
        $sourceId = (int) ($payload['source_id'] ?? 0);

        if ($sourceType === '' || $sourceId < 1) {
            return;
        }

        $previous = self::$muted;
        self::$muted = true;

        try {
            if (! Schema::hasColumn('operation_service_statistics', 'service_status')) {
                unset($payload['service_status']);
            }

            OperationServiceStatistic::query()->updateOrCreate(
                [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
                $payload,
            );
        } finally {
            self::$muted = $previous;
        }
    }

    private static function forgetSource(string $sourceType, int $sourceId): void
    {
        if ($sourceType === '' || $sourceId < 1) {
            return;
        }

        OperationServiceStatistic::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->delete();
    }

    private static function forgetStandaloneCoordination(int $coordinationId): void
    {
        if ($coordinationId < 1) {
            return;
        }

        OperationServiceStatistic::query()
            ->where('source_id', $coordinationId)
            ->whereIn('source_type', [
                OperationServiceStatistic::SOURCE_AMBULANCE,
                OperationServiceStatistic::SOURCE_CLINIC_ADMISSION,
                OperationServiceStatistic::SOURCE_TPA_RETAIL,
            ])
            ->delete();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Model>
     */
    private static function itemsForCase(int $caseId)
    {
        return collect()
            ->concat(TelemedicinePatientLab::query()->where('telemedicine_case_id', $caseId)->orderBy('id')->get())
            ->concat(TelemedicinePatientMedications::query()->where('telemedicine_case_id', $caseId)->orderBy('id')->get())
            ->concat(TelemedicinePatientStudy::query()->where('telemedicine_case_id', $caseId)->orderBy('id')->get())
            ->concat(TelemedicinePatientSpecialty::query()->where('telemedicine_case_id', $caseId)->orderBy('id')->get());
    }

    /**
     * @return \Illuminate\Support\Collection<int, Model>
     */
    private static function itemsForCoordination(int $coordinationId)
    {
        if ($coordinationId < 1) {
            return collect();
        }

        return collect()
            ->concat(TelemedicinePatientLab::query()->where('operation_coordination_service_id', $coordinationId)->orderBy('id')->get())
            ->concat(TelemedicinePatientMedications::query()->where('operation_coordination_service_id', $coordinationId)->orderBy('id')->get())
            ->concat(TelemedicinePatientStudy::query()->where('operation_coordination_service_id', $coordinationId)->orderBy('id')->get())
            ->concat(TelemedicinePatientSpecialty::query()->where('operation_coordination_service_id', $coordinationId)->orderBy('id')->get());
    }

    private static function coordinationForItem(Model $item): ?OperationCoordinationService
    {
        $loaded = $item->relationLoaded('operationCoordinationService')
            ? $item->getRelation('operationCoordinationService')
            : null;
        if ($loaded instanceof OperationCoordinationService) {
            return $loaded;
        }

        $id = (int) ($item->getAttribute('operation_coordination_service_id') ?? 0);
        if ($id < 1) {
            return null;
        }

        $coordination = OperationCoordinationService::query()->find($id);
        if ($coordination instanceof OperationCoordinationService) {
            $item->setRelation('operationCoordinationService', $coordination);
        }

        return $coordination;
    }

    private static function caseFor(?Model $source, ?OperationCoordinationService $coordination): ?TelemedicineCase
    {
        foreach ([$source, $coordination] as $model) {
            if (! $model instanceof Model) {
                continue;
            }

            $loaded = $model->relationLoaded('telemedicineCase') ? $model->getRelation('telemedicineCase') : null;
            if ($loaded instanceof TelemedicineCase) {
                return $loaded;
            }
        }

        $caseId = (int) ($source?->getAttribute('telemedicine_case_id') ?? $coordination?->telemedicine_case_id ?? 0);

        return $caseId > 0 ? TelemedicineCase::query()->find($caseId) : null;
    }

    private static function patientFor(?TelemedicineCase $case, ?OperationCoordinationService $coordination, Model $source): ?TelemedicinePatient
    {
        foreach ([$source, $coordination, $case] as $model) {
            if (! $model instanceof Model) {
                continue;
            }

            if ($model instanceof TelemedicinePatient) {
                return $model;
            }

            $loaded = $model->relationLoaded('telemedicinePatient') ? $model->getRelation('telemedicinePatient') : null;
            if ($loaded instanceof TelemedicinePatient) {
                return $loaded;
            }
        }

        $patientId = (int) ($source->getAttribute('telemedicine_patient_id')
            ?? $coordination?->telemedicine_patient_id
            ?? $case?->telemedicine_patient_id
            ?? 0);

        if ($patientId < 1) {
            return null;
        }

        return TelemedicinePatient::query()
            ->with(['businessLine', 'state.region', 'city', 'afilliation.agency', 'afilliation.agent', 'afilliationCorporate.agency', 'afilliationCorporate.agent'])
            ->find($patientId);
    }

    private static function consultationFor(?TelemedicineCase $case, ?OperationCoordinationService $coordination, Model $source): ?TelemedicineConsultationPatient
    {
        $consultationId = (int) ($source->getAttribute('telemedicine_consultation_patient_id')
            ?? $coordination?->telemedicine_consultation_patient_id
            ?? 0);

        if ($consultationId > 0) {
            $consultation = TelemedicineConsultationPatient::query()->find($consultationId);
            if ($consultation instanceof TelemedicineConsultationPatient) {
                return $consultation;
            }
        }

        $caseId = (int) ($case?->id ?? 0);
        if ($caseId < 1) {
            return null;
        }

        return TelemedicineInitialDiagnosisUpdater::findInitialConsultation($caseId)
            ?? TelemedicineConsultationPatient::query()->where('telemedicine_case_id', $caseId)->orderBy('id')->first();
    }

    private static function itemName(Model $item, string $sourceType): string
    {
        $name = match ($sourceType) {
            OperationServiceStatistic::SOURCE_LAB => $item->getAttribute('laboratory'),
            OperationServiceStatistic::SOURCE_MEDICATION => $item->getAttribute('medicine'),
            OperationServiceStatistic::SOURCE_STUDY => $item->getAttribute('study'),
            OperationServiceStatistic::SOURCE_SPECIALTY => $item->getAttribute('specialty'),
            default => null,
        };

        return self::filledText($name) ?? 'Servicio no especificado';
    }

    private static function channelForSource(string $sourceType, ?OperationCoordinationService $coordination): string
    {
        $fromCoordination = self::filledText($coordination?->servicie)
            ?? self::filledText($coordination?->specific_service)
            ?? self::filledText($coordination?->general_service);

        if ($fromCoordination !== null) {
            return $fromCoordination;
        }

        return match ($sourceType) {
            OperationServiceStatistic::SOURCE_LAB => 'LABORATORIOS',
            OperationServiceStatistic::SOURCE_MEDICATION => 'MEDICAMENTOS',
            OperationServiceStatistic::SOURCE_STUDY => 'IMAGENOLOGIA',
            OperationServiceStatistic::SOURCE_SPECIALTY => 'ESPECIALISTA',
            OperationServiceStatistic::SOURCE_AMBULANCE => 'TRASLADO EN AMBULANCIA',
            OperationServiceStatistic::SOURCE_CLINIC_ADMISSION => 'INGRESO A CLINICA',
            default => 'TPA/RETAIL',
        };
    }

    private static function itemIsCovered(Model $item, string $sourceType): ?bool
    {
        if ($sourceType === OperationServiceStatistic::SOURCE_MEDICATION && $item instanceof TelemedicinePatientMedications) {
            return TelemedicineMedicationCoverage::isCovered($item);
        }

        $type = $item->getAttribute('type');
        if (is_string($type) && trim($type) !== '') {
            return mb_strtoupper(trim($type)) === 'CUBIERTO';
        }

        return null;
    }

    private static function resolveServiceOrder(?OperationCoordinationService $coordination, string $itemName, string $channel): ?OperationServiceOrder
    {
        if (! $coordination instanceof OperationCoordinationService) {
            return null;
        }

        $orders = $coordination->operationServiceOrders()->with('operationServiceOrderItems')->orderByDesc('id')->get();
        if ($orders->isEmpty()) {
            return null;
        }

        $normalizedItem = mb_strtolower(trim($itemName));
        $normalizedChannel = mb_strtoupper(trim($channel));

        foreach ($orders as $order) {
            $orderType = mb_strtoupper(trim((string) ($order->service_type ?? '')));
            if ($normalizedChannel !== '' && $orderType !== '' && $orderType !== $normalizedChannel) {
                continue;
            }

            foreach ($order->operationServiceOrderItems as $osItem) {
                if (mb_strtolower(trim((string) $osItem->item_name)) === $normalizedItem) {
                    return $order;
                }
            }
        }

        $typed = $orders->first(function (OperationServiceOrder $order) use ($normalizedChannel): bool {
            $orderType = mb_strtoupper(trim((string) ($order->service_type ?? '')));

            return $normalizedChannel !== '' && $orderType === $normalizedChannel;
        });

        return $typed instanceof OperationServiceOrder ? $typed : $orders->first();
    }

    private static function startedDateTime(?TelemedicineCase $case, ?OperationCoordinationService $coordination, Model $source): ?Carbon
    {
        foreach ([$case?->created_at, $coordination?->date_solicitud, $source->getAttribute('created_at')] as $value) {
            $parsed = self::toCarbon($value);
            if ($parsed instanceof Carbon) {
                return $parsed;
            }
        }

        return null;
    }

    private static function lastTouchedBy(?TelemedicineCase $case, ?OperationCoordinationService $coordination): ?string
    {
        $candidates = [];

        if ($case instanceof TelemedicineCase) {
            $observation = ObservationCase::query()
                ->with('createdBy')
                ->where('telemedicine_case_id', $case->id)
                ->orderByDesc('id')
                ->first();

            if ($observation instanceof ObservationCase) {
                $candidates[] = [
                    'at' => self::toCarbon($observation->created_at) ?? now()->subCentury(),
                    'name' => self::resolvePersonName($observation->createdBy?->name ?? $observation->created_by),
                ];
            }
        }

        $latestCoordination = $coordination;
        if ($case instanceof TelemedicineCase) {
            $fromCase = OperationCoordinationService::query()
                ->where('telemedicine_case_id', $case->id)
                ->orderByDesc('updated_at')
                ->first();
            if ($fromCase instanceof OperationCoordinationService) {
                $latestCoordination = $fromCase;
            }
        }

        if ($latestCoordination instanceof OperationCoordinationService) {
            $candidates[] = [
                'at' => self::toCarbon($latestCoordination->updated_at) ?? now()->subCentury(),
                'name' => self::resolvePersonName($latestCoordination->updated_by),
            ];
        }

        usort($candidates, static fn (array $left, array $right): int => $right['at'] <=> $left['at']);

        foreach ($candidates as $candidate) {
            if (is_string($candidate['name']) && $candidate['name'] !== '') {
                return $candidate['name'];
            }
        }

        return self::resolvePersonName($case?->managed_by);
    }

    private static function initialDiagnosis(?TelemedicineCase $case, ?TelemedicineConsultationPatient $consultation): ?string
    {
        $caseId = (int) ($case?->id ?? 0);
        if ($caseId > 0) {
            $fromInitial = TelemedicineInitialDiagnosisUpdater::currentDiagnosis($caseId);
            if ($fromInitial !== '') {
                return $fromInitial;
            }
        }

        return self::filledText($consultation?->diagnostic_impression);
    }

    private static function finalDiagnosis(?TelemedicineCase $case, ?TelemedicineConsultationPatient $consultation): ?string
    {
        $caseId = (int) ($case?->id ?? 0);

        if ($caseId > 0) {
            if (self::hasColumn('telemedicine_follow_ups', 'diagnostic_impression')) {
                $followUp = TelemedicineFollowUp::query()
                    ->where('telemedicine_case_id', $caseId)
                    ->whereNotNull('diagnostic_impression')
                    ->orderByDesc('id')
                    ->value('diagnostic_impression');

                $fromFollowUp = self::filledText($followUp);
                if ($fromFollowUp !== null) {
                    return $fromFollowUp;
                }
            }

            if (self::hasColumn('telemedicine_amd_informs', 'diagnostic_impression')) {
                $amd = TelemedicineAmdInform::query()
                    ->where('telemedicine_case_id', $caseId)
                    ->whereNotNull('diagnostic_impression')
                    ->orderByDesc('id')
                    ->value('diagnostic_impression');

                $fromAmd = self::filledText($amd);
                if ($fromAmd !== null) {
                    return $fromAmd;
                }
            }

            $latestConsultation = TelemedicineConsultationPatient::query()
                ->where('telemedicine_case_id', $caseId)
                ->whereNotNull('diagnostic_impression')
                ->orderByDesc('id')
                ->value('diagnostic_impression');

            $fromLatest = self::filledText($latestConsultation);
            if ($fromLatest !== null) {
                return $fromLatest;
            }
        }

        return self::initialDiagnosis($case, $consultation);
    }

    private static function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    private static function businessLineName(?TelemedicinePatient $patient, ?OperationCoordinationService $coordination): ?string
    {
        $fromPatient = self::filledText($patient?->businessLine?->definition);
        if ($fromPatient !== null) {
            return $fromPatient;
        }

        $fromCoordination = $coordination?->businessLine?->definition ?? null;
        if (self::filledText($fromCoordination) !== null) {
            return self::filledText($fromCoordination);
        }

        $lineId = (int) ($patient?->business_line_id ?? $coordination?->business_line_id ?? 0);
        if ($lineId < 1) {
            return null;
        }

        return self::filledText(\App\Models\BusinessLine::query()->whereKey($lineId)->value('definition'));
    }

    private static function regionName(?TelemedicinePatient $patient, ?OperationCoordinationService $coordination): ?string
    {
        $fromPatient = self::filledText($patient?->getAttributes()['region'] ?? null);
        if ($fromPatient !== null) {
            return $fromPatient;
        }

        return self::geoName($patient?->state?->region ?? $coordination?->state?->region);
    }

    private static function geoName(mixed $geo): ?string
    {
        if (! is_object($geo)) {
            return null;
        }

        return self::filledText($geo->definition ?? $geo->name ?? null);
    }

    private static function serviceProviderName(?OperationCoordinationService $coordination): ?string
    {
        if (! $coordination instanceof OperationCoordinationService) {
            return null;
        }

        $fromRelation = self::filledText($coordination->supplier?->name ?? $coordination->supplier?->razon_social);
        if ($fromRelation !== null) {
            return $fromRelation;
        }

        return self::filledText($coordination->supplier_service);
    }

    private static function medicalProviderName(?OperationCoordinationService $coordination, ?OperationServiceOrder $order, ?TelemedicineCase $case): ?string
    {
        $fromOrder = self::filledText($order?->doctorNurse?->name);
        if ($fromOrder !== null) {
            return $fromOrder;
        }

        $doctor = $coordination?->telemedicineDoctor ?? $case?->telemedicineDoctor;
        $fromDoctor = self::filledText($doctor?->full_name);
        if ($fromDoctor !== null) {
            return $fromDoctor;
        }

        return null;
    }

    private static function relationshipForPatient(?TelemedicinePatient $patient): ?string
    {
        if (! $patient instanceof TelemedicinePatient) {
            return null;
        }

        $corporate = TelemedicinePatientPlanBridge::linkedAffiliateCorporate($patient);
        if ($corporate instanceof AffiliateCorporate) {
            return self::filledText($corporate->relationship);
        }

        $individual = TelemedicinePatientPlanBridge::linkedAffiliate($patient);
        if ($individual instanceof Affiliate) {
            return self::filledText($individual->relationship);
        }

        return null;
    }

    private static function resolvePersonName(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $name = User::query()->whereKey((int) $raw)->value('name');

            return self::filledText($name) ?? $raw;
        }

        return self::filledText($raw);
    }

    private static function caseDeniedLabel(mixed $status, mixed $explicit): string
    {
        if (self::isYes($explicit)) {
            return self::YES;
        }

        $normalized = mb_strtoupper(trim((string) $status));
        foreach (self::DENIED_STATUS_TOKENS as $token) {
            if ($normalized !== '' && str_contains($normalized, $token)) {
                return self::YES;
            }
        }

        return self::NO;
    }

    private static function yesNoFromFlag(mixed $flag, mixed $percent, mixed $amount): string
    {
        if (self::isYes($flag) || (is_numeric($percent) && (float) $percent > 0) || (is_numeric($amount) && (float) $amount > 0)) {
            return self::YES;
        }

        return self::NO;
    }

    private static function isYes(mixed $value): bool
    {
        $normalized = mb_strtoupper(trim((string) $value));

        return in_array($normalized, ['SI', 'SÍ', 'YES', 'TRUE', '1'], true);
    }

    private static function isTitularRelationship(mixed $value): bool
    {
        return mb_strtoupper(trim((string) $value)) === 'TITULAR';
    }

    private static function filledText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        foreach (self::PLACEHOLDERS as $placeholder) {
            if (mb_strtoupper($text) === mb_strtoupper($placeholder)) {
                return null;
            }
        }

        return $text;
    }

    private static function nullableId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private static function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private static function toDateString(mixed $value): ?string
    {
        return self::toCarbon($value)?->toDateString();
    }

    private static function toCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}/', $text) === 1) {
                return Carbon::createFromFormat('d/m/Y', substr($text, 0, 10)) ?: null;
            }

            return Carbon::parse($text);
        } catch (Throwable) {
            return null;
        }
    }
}
