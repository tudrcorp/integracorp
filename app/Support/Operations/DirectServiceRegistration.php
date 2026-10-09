<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationDirectServiceRegistration;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Models\TelemedicineServiceList;
use App\Models\User;
use App\Support\ClinicalEntitlements\AffiliateClinicalEntitlementResolver;
use App\Support\ClinicalEntitlements\ClinicalConsultationConsumption;
use App\Support\ClinicalEntitlements\ClinicalEntitlementException;
use App\Support\ClinicalEntitlements\ClinicalUsageLedger;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicineCaseFactory;
use App\Support\Telemedicine\TelemedicineCaseIdentity;
use App\Support\Telemedicine\TelemedicineCoverageCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registro directo de servicios médicos: el camino de Operaciones para dejar
 * registrados laboratorios, estudios, especialistas, medicamentos, traslado en
 * ambulancia o ingreso a clínica sin pasar por la consulta médica.
 *
 * Produce exactamente lo mismo que el flujo médico para que el resto del
 * sistema (gestión, órdenes de servicio, cotizaciones, estadísticas, cuentas
 * por cobrar y por pagar, reversos) no distinga el origen:
 *
 * - un caso del paciente (nuevo con estatus REGISTRO DIRECTO, o uno abierto);
 * - una coordinación por categoría, con sus ítems enlazados desde el inicio;
 * - el consumo de cupo clínico con la misma regla de la consulta: solo lo
 *   cubierto, una vez por categoría y caso; sin cupo se bloquea (Operaciones no
 *   puede autorizar excesos) y el ítem puede registrarse como NO CUBIERTO;
 * - la traza: fila en `operation_direct_service_registrations`, bitácora del
 *   caso y auditoría de seguridad.
 *
 * Todo ocurre en una transacción: si un paso falla no queda nada a medias.
 */
final class DirectServiceRegistration
{
    public const COVERED = 'CUBIERTO';

    public const NOT_COVERED = 'NO CUBIERTO';

    public const CASE_STATUS = 'REGISTRO DIRECTO';

    public const CASE_MODE_NEW = 'new';

    public const CASE_MODE_EXISTING = 'existing';

    /** Estatus de caso que ya no admite servicios. */
    public const CLOSED_CASE_STATUSES = ['ALTA MEDICA', 'ALTA MAEDICA', 'ELIMINADO'];

    /** Servicios sin catálogo de ítems que se registran como un único «Servicio». */
    public const STANDALONE_SERVICES = ['TRASLADO EN AMBULANCIA', 'INGRESO A CLINICA'];

    public const MAX_ITEMS_PER_CATEGORY = 50;

    /**
     * @return array<string, array{label: string, specific_service: string, model: class-string, column: string, catalog: class-string|null, channel: ClinicalServiceChannel|null}>
     */
    public static function categories(): array
    {
        return [
            'labs' => [
                'label' => 'Laboratorios',
                'specific_service' => 'LABORATORIOS',
                'model' => TelemedicinePatientLab::class,
                'column' => 'laboratory',
                'catalog' => TelemedicineListLaboratory::class,
                'channel' => ClinicalServiceChannel::Laboratory,
            ],
            'studies' => [
                'label' => 'Estudios (imagenología)',
                'specific_service' => 'IMAGENOLOGIA',
                'model' => TelemedicinePatientStudy::class,
                'column' => 'study',
                'catalog' => TelemedicineListStudy::class,
                'channel' => ClinicalServiceChannel::Imaging,
            ],
            'specialists' => [
                'label' => 'Consultas con especialista',
                'specific_service' => 'ESPECIALISTA',
                'model' => TelemedicinePatientSpecialty::class,
                'column' => 'specialty',
                'catalog' => TelemedicineListSpecialist::class,
                'channel' => ClinicalServiceChannel::Specialist,
            ],
            'medications' => [
                'label' => 'Medicamentos',
                'specific_service' => 'MEDICAMENTOS',
                'model' => TelemedicinePatientMedications::class,
                'column' => 'medicine',
                'catalog' => null,
                'channel' => ClinicalServiceChannel::Medication,
            ],
        ];
    }

    /**
     * Pacientes que el analista puede ver (mismo alcance por proveedor que el resto de Operaciones).
     *
     * @return Builder<TelemedicinePatient>
     */
    public static function visiblePatientsQuery(): Builder
    {
        return OperationsSupplierScope::applyToQuery(TelemedicinePatient::query(), 'supplier_id');
    }

    /**
     * @return array<int, string>
     */
    public static function searchPatients(string $search, int $limit = 30): array
    {
        $search = trim($search);

        if (mb_strlen($search) < 2) {
            return [];
        }

        return self::visiblePatientsQuery()
            ->where(function (Builder $query) use ($search): void {
                $query->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('nro_identificacion', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['id', 'full_name', 'nro_identificacion', 'code'])
            ->mapWithKeys(fn (TelemedicinePatient $patient): array => [$patient->id => self::patientLabel($patient)])
            ->all();
    }

    public static function patientLabel(TelemedicinePatient $patient): string
    {
        return collect([
            $patient->full_name,
            filled($patient->nro_identificacion) ? 'C.I. '.$patient->nro_identificacion : null,
            $patient->code,
        ])->filter()->implode(' · ');
    }

    /**
     * @return array<int, string>
     */
    public static function openCaseOptions(?int $patientId): array
    {
        if ($patientId === null) {
            return [];
        }

        return TelemedicineCase::query()
            ->where('telemedicine_patient_id', $patientId)
            ->whereNotIn('status', self::CLOSED_CASE_STATUSES)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'code', 'status', 'created_at'])
            ->mapWithKeys(fn (TelemedicineCase $case): array => [
                $case->id => trim($case->code.' · '.$case->status.' · '.$case->created_at?->format('d/m/Y')),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function serviceLineOptions(): array
    {
        return TelemedicineServiceList::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (mixed $name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $name): array => [$name => $name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function doctorOptions(): array
    {
        return TelemedicineDoctor::query()
            ->where('status', 'ACTIVO')
            ->orderBy('full_name')
            ->pluck('full_name', 'id')
            ->all();
    }

    /**
     * @param  class-string  $catalog
     * @return array<string, string>
     */
    public static function catalogOptions(string $catalog): array
    {
        return $catalog::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (mixed $name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $name): array => [$name => $name])
            ->all();
    }

    /**
     * Cobertura que propone el catálogo para un ítem (el analista puede cambiarla).
     */
    public static function suggestedCoverage(string $category, ?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return self::COVERED;
        }

        $covered = match ($category) {
            'labs' => TelemedicineCoverageCatalog::laboratoryIsCovered($name),
            'studies' => TelemedicineCoverageCatalog::studyIsCovered($name),
            'specialists' => TelemedicineCoverageCatalog::specialistIsCovered($name),
            default => true,
        };

        return $covered ? self::COVERED : self::NOT_COVERED;
    }

    /**
     * Cupo clínico por categoría para mostrarlo antes de registrar.
     *
     * `kind`: quota (con tope), unlimited (sin tope), free (no está en el plan pero
     * puede registrarse sin cupo), not_included (lo cubierto se bloquea), no_plan.
     * `tone`: success, warning, danger o gray.
     *
     * @return array{has_plan: bool, is_complete: bool, message: string|null, rows: list<array{category: string, label: string, kind: string, status: string, detail: string, tone: string, used: int|null, quota: int|null, remaining: int|null, unit: string|null, scope: string|null, already_counted: bool}>}
     */
    public static function quotaPreview(TelemedicinePatient $patient, ?int $caseId = null): array
    {
        $snapshot = AffiliateClinicalEntitlementResolver::forPatient($patient);
        $rows = [];

        foreach (self::categories() as $key => $config) {
            $channel = $config['channel'];
            $entitlement = $snapshot->hasPlan ? $snapshot->forChannel($channel) : null;
            $row = [
                'category' => $key,
                'label' => $config['label'],
                'used' => null,
                'quota' => null,
                'remaining' => null,
                'unit' => null,
                'scope' => null,
                'already_counted' => false,
            ];

            if (! $snapshot->hasPlan) {
                $rows[] = [...$row, 'kind' => 'no_plan', 'status' => 'Sin plan clínico', 'detail' => 'No descuenta cupo.', 'tone' => 'gray'];

                continue;
            }

            if ($entitlement === null) {
                $free = ClinicalConsultationConsumption::unmappedChannelMayProceedWithoutQuota($channel->value);
                $rows[] = [
                    ...$row,
                    'kind' => $free ? 'free' : 'not_included',
                    'status' => $free ? 'Sin límite' : 'No incluido',
                    'detail' => $free ? 'Se registra sin descontar cupo.' : 'Lo cubierto se bloquea: regístrelo como no cubierto.',
                    'tone' => $free ? 'gray' : 'danger',
                ];

                continue;
            }

            if ($entitlement->quotaScope === ClinicalQuotaScope::Unlimited) {
                $rows[] = [...$row, 'kind' => 'unlimited', 'status' => 'Sin límite', 'detail' => 'Sin tope de usos en este plan.', 'tone' => 'gray', 'scope' => $entitlement->quotaScope->label()];

                continue;
            }

            $alreadyCounted = $caseId !== null && ! ClinicalUsageLedger::shouldConsumeForScope($entitlement, $patient, $caseId);
            $remaining = (int) ($entitlement->remaining ?? 0);

            $rows[] = [
                ...$row,
                'kind' => 'quota',
                'status' => $entitlement->operationsBalanceLabel(),
                'detail' => match (true) {
                    $alreadyCounted => 'Este caso ya consumió esta categoría: no descuenta otra vez.',
                    $entitlement->exhausted => 'Sin cupo: lo cubierto se bloquea; regístrelo como no cubierto.',
                    default => 'Lo cubierto descuenta 1 por caso.',
                },
                'tone' => match (true) {
                    $alreadyCounted => 'gray',
                    $entitlement->exhausted => 'danger',
                    $remaining <= 1 => 'warning',
                    default => 'success',
                },
                'used' => (int) $entitlement->used,
                'quota' => (int) $entitlement->quota,
                'remaining' => $remaining,
                'unit' => $entitlement->quotaScope === ClinicalQuotaScope::DistinctCases ? 'casos' : 'usos',
                'scope' => $entitlement->quotaScope->label(),
                'already_counted' => $alreadyCounted,
            ];
        }

        return [
            'has_plan' => $snapshot->hasPlan,
            'is_complete' => ! $snapshot->hasPlan || $snapshot->isComplete,
            'message' => $snapshot->hasPlan && ! $snapshot->isComplete ? $snapshot->blockingMessage : null,
            'rows' => $rows,
        ];
    }

    /**
     * Limpia y valida lo que llega del formulario.
     *
     * @param  array<string, mixed>  $data
     * @return array{patient_id: int, case_mode: string, case_id: int|null, service_line: string, diagnosis: string, request_date: CarbonImmutable, service_date: CarbonImmutable, observations: string|null, doctor_id: int|null, labs: list<array{name: string, coverage: string}>, studies: list<array{name: string, coverage: string}>, specialists: list<array{name: string, coverage: string}>, medications: list<array{name: string, coverage: string, quantity: int, duration: int|null, indications: string}>, standalone: list<array{name: string, coverage: string}>}
     */
    public static function normalize(array $data): array
    {
        $patientId = (int) ($data['telemedicine_patient_id'] ?? 0);

        if ($patientId <= 0) {
            throw DirectServiceRegistrationException::field('telemedicine_patient_id', 'Seleccione el paciente.');
        }

        $caseMode = ($data['case_mode'] ?? self::CASE_MODE_NEW) === self::CASE_MODE_EXISTING ? self::CASE_MODE_EXISTING : self::CASE_MODE_NEW;
        $caseId = $caseMode === self::CASE_MODE_EXISTING ? (int) ($data['telemedicine_case_id'] ?? 0) : null;

        if ($caseMode === self::CASE_MODE_EXISTING && ($caseId === null || $caseId <= 0)) {
            throw DirectServiceRegistrationException::field('telemedicine_case_id', 'Seleccione el caso abierto al que se suma el servicio.');
        }

        $serviceLine = trim((string) ($data['service_line'] ?? ''));

        if ($serviceLine === '' || ! array_key_exists($serviceLine, self::serviceLineOptions())) {
            throw DirectServiceRegistrationException::field('service_line', 'Seleccione una línea de servicio válida.');
        }

        $diagnosis = trim((string) ($data['diagnosis'] ?? ''));

        if (mb_strlen($diagnosis) < 5) {
            throw DirectServiceRegistrationException::field('diagnosis', 'Escriba el diagnóstico o motivo del servicio (mínimo 5 caracteres).');
        }

        $requestDate = self::parseDate($data['request_date'] ?? null, 'request_date', 'fecha de solicitud');
        $serviceDate = self::parseDate($data['service_date'] ?? null, 'service_date', 'fecha de servicio');

        if ($serviceDate->lt($requestDate)) {
            throw DirectServiceRegistrationException::field('service_date', 'La fecha de servicio no puede ser anterior a la fecha de solicitud.');
        }

        $normalized = [
            'patient_id' => $patientId,
            'case_mode' => $caseMode,
            'case_id' => $caseId,
            'service_line' => $serviceLine,
            'diagnosis' => Str::limit($diagnosis, 2000, ''),
            'request_date' => $requestDate,
            'service_date' => $serviceDate,
            'observations' => filled($data['observations'] ?? null) ? Str::limit(trim((string) $data['observations']), 2000, '') : null,
            'doctor_id' => filled($data['prescribing_doctor_id'] ?? null) ? (int) $data['prescribing_doctor_id'] : null,
            'labs' => self::catalogRows($data['labs'] ?? [], 'labs'),
            'studies' => self::catalogRows($data['studies'] ?? [], 'studies'),
            'specialists' => self::catalogRows($data['specialists'] ?? [], 'specialists'),
            'medications' => self::medicationRows($data['medications'] ?? []),
            'standalone' => self::standaloneRows($data['standalone'] ?? []),
        ];

        if (self::itemCount($normalized) === 0) {
            throw new DirectServiceRegistrationException('Agregue al menos un laboratorio, estudio, especialista, medicamento o servicio.');
        }

        if ($normalized['medications'] !== []) {
            if ($normalized['doctor_id'] === null) {
                throw DirectServiceRegistrationException::field('prescribing_doctor_id', 'Seleccione el médico que indicó los medicamentos.');
            }

            if (! TelemedicineDoctor::query()->whereKey($normalized['doctor_id'])->where('status', 'ACTIVO')->exists()) {
                throw DirectServiceRegistrationException::field('prescribing_doctor_id', 'El médico seleccionado no existe o no está activo.');
            }
        } else {
            $normalized['doctor_id'] = null;
        }

        return $normalized;
    }

    /**
     * Registra todo en una transacción y devuelve la traza creada.
     *
     * @param  array<string, mixed>  $data
     */
    public static function register(array $data, User $actor): OperationDirectServiceRegistration
    {
        $input = self::normalize($data);

        $lock = Cache::lock('direct-service-registration:patient:'.$input['patient_id'], 60);

        if (! $lock->get()) {
            throw new DirectServiceRegistrationException('Ya hay un registro directo en curso para este paciente. Espere unos segundos e intente de nuevo.');
        }

        try {
            $registration = DB::transaction(fn (): OperationDirectServiceRegistration => self::persist($input, $actor));
        } catch (ClinicalEntitlementException $exception) {
            throw new DirectServiceRegistrationException($exception->getMessage().' Registre ese ítem como NO CUBIERTO para continuar.');
        } finally {
            $lock->release();
        }

        AffiliateClinicalEntitlementResolver::flush($input['patient_id']);

        SecurityAudit::log('AUDIT_OPERATIONS_DIRECT_SERVICE_REGISTERED', 'operations.coordination-services.direct-registration', [
            'registration_id' => $registration->id,
            'telemedicine_patient_id' => $registration->telemedicine_patient_id,
            'telemedicine_case_id' => $registration->telemedicine_case_id,
            'case_created' => $registration->case_created,
            'coordination_ids' => $registration->coordination_ids,
            'clinical_usage_ids' => $registration->clinical_usage_ids,
            'items_count' => count($registration->items),
        ], $actor);

        return $registration;
    }

    /**
     * @param  array<string, mixed>  $input  resultado de {@see normalize()}
     */
    private static function persist(array $input, User $actor): OperationDirectServiceRegistration
    {
        /** El bloqueo del paciente serializa también el consumo de cupo. */
        $patient = self::visiblePatientsQuery()->whereKey($input['patient_id'])->lockForUpdate()->first();

        if (! $patient instanceof TelemedicinePatient) {
            throw DirectServiceRegistrationException::field('telemedicine_patient_id', 'El paciente no existe o no está en su alcance.');
        }

        $existingCase = null;

        if ($input['case_mode'] === self::CASE_MODE_EXISTING) {
            $existingCase = TelemedicineCase::query()->whereKey($input['case_id'])->lockForUpdate()->first();

            if (! $existingCase instanceof TelemedicineCase || (int) $existingCase->telemedicine_patient_id !== (int) $patient->id) {
                throw DirectServiceRegistrationException::field('telemedicine_case_id', 'El caso seleccionado no pertenece a este paciente.');
            }

            if (in_array(mb_strtoupper(trim((string) $existingCase->status)), self::CLOSED_CASE_STATUSES, true)) {
                throw DirectServiceRegistrationException::field('telemedicine_case_id', 'El caso seleccionado ya está cerrado. Elija otro o cree uno nuevo.');
            }
        }

        self::assertQuota($patient, $existingCase?->id, $input);

        $case = $existingCase ?? TelemedicineCaseFactory::createForPatient($patient, [
            'reason' => 'REGISTRO DIRECTO DE SERVICIOS',
            'status' => self::CASE_STATUS,
            'assigned_by' => $actor->name,
            'managed_by' => $patient->managed_by,
            'supplier_id' => OperationsSupplierScope::resolveFromPatient($patient),
        ]);

        $registration = OperationDirectServiceRegistration::query()->create([
            'telemedicine_patient_id' => $patient->id,
            'telemedicine_case_id' => $case->id,
            'case_created' => $existingCase === null,
            'registered_by_user_id' => $actor->id,
            'registered_by_name' => $actor->name,
            'service_line' => $input['service_line'],
            'diagnosis' => $input['diagnosis'],
            'request_date' => $input['request_date']->toDateString(),
            'service_date' => $input['service_date']->toDateString(),
            'observations' => $input['observations'],
            'prescribing_doctor_id' => $input['doctor_id'],
            'items' => [],
            'coordination_ids' => [],
            'clinical_usage_ids' => [],
        ]);

        $coordinationIds = [];
        $itemsTrace = [];
        $coordinationByCategory = [];

        foreach (self::categories() as $key => $config) {
            if ($input[$key] === []) {
                continue;
            }

            $coordination = self::createCoordination($patient, $case, $registration, $input, $config['specific_service'], $actor);
            $coordinationIds[] = $coordination->id;
            $coordinationByCategory[$key] = $coordination;

            foreach ($input[$key] as $row) {
                $item = $key === 'medications'
                    ? self::createMedication($patient, $case, $coordination, $row, (int) $input['doctor_id'])
                    : self::createCatalogItem($config, $patient, $case, $coordination, $row, $actor);

                $itemsTrace[] = [
                    'category' => $config['label'],
                    'specific_service' => $config['specific_service'],
                    'name' => $row['name'],
                    'coverage' => $row['coverage'],
                    'item_id' => $item->getKey(),
                    'item_table' => $item->getTable(),
                    'coordination_id' => $coordination->id,
                ] + ($key === 'medications' ? ['quantity' => $row['quantity'], 'duration' => $row['duration'], 'indications' => $row['indications']] : []);
            }
        }

        foreach ($input['standalone'] as $row) {
            $coordination = self::createCoordination($patient, $case, $registration, $input, $row['name'], $actor);
            $coordinationIds[] = $coordination->id;

            $item = TelemedicinePatientSpecialty::query()->create([
                'telemedicine_patient_id' => $patient->id,
                'telemedicine_case_id' => $case->id,
                'type' => $row['coverage'],
                'specialty' => $row['name'],
                'assigned_by' => (string) $actor->id,
                'status' => 'PENDIENTE',
                'operation_coordination_service_id' => $coordination->id,
            ]);

            $itemsTrace[] = [
                'category' => 'Servicio',
                'specific_service' => $row['name'],
                'name' => $row['name'],
                'coverage' => $row['coverage'],
                'item_id' => $item->id,
                'item_table' => $item->getTable(),
                'coordination_id' => $coordination->id,
            ];
        }

        $usageIds = self::consumeQuota($patient, $case, $input, $coordinationByCategory);

        $registration->forceFill([
            'items' => $itemsTrace,
            'coordination_ids' => $coordinationIds,
            'clinical_usage_ids' => $usageIds,
        ])->save();

        ObservationCase::query()->create([
            'telemedicine_case_id' => $case->id,
            'description' => self::bitacoraText($registration, $itemsTrace, $usageIds, $actor),
            'created_by' => (string) $actor->id,
        ]);

        return $registration->refresh();
    }

    /**
     * Mismo criterio que la consulta médica, pero sin autorización de exceso:
     * un canal cubierto sin cupo, o no incluido en el plan, bloquea.
     *
     * @param  array<string, mixed>  $input
     */
    public static function assertQuota(TelemedicinePatient $patient, ?int $caseId, array $input): void
    {
        $channels = self::coveredChannels($input);

        if ($channels === []) {
            return;
        }

        $snapshot = AffiliateClinicalEntitlementResolver::forPatient($patient);

        if (! $snapshot->hasPlan) {
            return;
        }

        if (! $snapshot->isComplete) {
            throw new DirectServiceRegistrationException($snapshot->blockingMessage);
        }

        foreach ($channels as $category => $channel) {
            $label = self::categories()[$category]['label'];
            $entitlement = $snapshot->forChannel($channel);

            if ($entitlement === null) {
                if (ClinicalConsultationConsumption::unmappedChannelMayProceedWithoutQuota($channel->value)) {
                    continue;
                }

                throw DirectServiceRegistrationException::field($category, "{$label}: el plan del paciente no incluye este servicio en su uso clínico. Registre los ítems como NO CUBIERTO.");
            }

            if ($entitlement->exhausted && ClinicalUsageLedger::shouldConsumeForScope($entitlement, $patient, $caseId)) {
                throw DirectServiceRegistrationException::field($category, "{$label}: sin cupo disponible ({$entitlement->operationsBalanceLabel()}). Registre los ítems como NO CUBIERTO.");
            }
        }
    }

    /**
     * Canales que consumen cupo: solo categorías con algún ítem cubierto.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, ClinicalServiceChannel>
     */
    public static function coveredChannels(array $input): array
    {
        $channels = [];

        foreach (self::categories() as $key => $config) {
            $hasCovered = collect($input[$key] ?? [])->contains(fn (array $row): bool => $row['coverage'] === self::COVERED);

            if ($hasCovered && $config['channel'] !== null) {
                $channels[$key] = $config['channel'];
            }
        }

        return $channels;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, OperationCoordinationService>  $coordinationByCategory
     * @return list<int>
     */
    private static function consumeQuota(TelemedicinePatient $patient, TelemedicineCase $case, array $input, array $coordinationByCategory): array
    {
        $snapshot = AffiliateClinicalEntitlementResolver::forPatient($patient);

        if (! $snapshot->hasPlan) {
            return [];
        }

        $usageIds = [];

        foreach (self::coveredChannels($input) as $category => $channel) {
            if ($snapshot->forChannel($channel) === null) {
                continue;
            }

            $usage = ClinicalUsageLedger::consume($patient, [
                'channel' => $channel,
                'telemedicine_case_id' => $case->id,
            ]);

            /** Si el caso ya había consumido este canal se devuelve ese consumo: no se reasigna. */
            if ($usage->operation_coordination_service_id === null && isset($coordinationByCategory[$category])) {
                $usage->forceFill(['operation_coordination_service_id' => $coordinationByCategory[$category]->id])->save();
            }

            $usageIds[] = (int) $usage->id;
        }

        return $usageIds;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function createCoordination(
        TelemedicinePatient $patient,
        TelemedicineCase $case,
        OperationDirectServiceRegistration $registration,
        array $input,
        string $specificService,
        User $actor,
    ): OperationCoordinationService {
        $identity = TelemedicineCaseIdentity::coordinationIdentity([], $patient);
        $now = CarbonImmutable::now();

        return OperationCoordinationService::query()->create([
            'telemedicine_patient_id' => $patient->id,
            'telemedicine_case_id' => $case->id,
            'telemedicine_doctor_id' => $specificService === 'MEDICAMENTOS' ? $input['doctor_id'] : null,
            'date_solicitud' => $input['request_date']->setTime($now->hour, $now->minute, $now->second)->format('Y-m-d H:i:s'),
            'date_service' => $input['service_date']->setTime($now->hour, $now->minute, $now->second)->format('Y-m-d H:i:s'),
            'business_line_id' => $patient->business_line_id,
            'business_unit_id' => $patient->business_unit_id,
            'reference_number' => (string) $case->code,
            'status' => 'PENDIENTE',
            'patient' => $identity['patient'],
            'ci_patient' => $identity['ci_patient'] ?? '...',
            'birth_date_patient' => $identity['birth_date_patient'],
            'relationship_patient' => $identity['relationship_patient'] ?? '...',
            'age_patient' => $identity['age_patient'],
            'contractor' => $patient->afilliation_id === null ? 'CORPORATIVO' : 'INDIVIDUAL',
            'state_id' => $patient->state_id,
            'city_id' => $patient->city_id,
            'address' => $patient->address,
            'phone_holder' => $patient->phone,
            'symptoms_diagnosis' => $input['diagnosis'],
            'servicie' => $input['service_line'],
            'specific_service' => $specificService,
            'type_negotiation' => '...',
            'status_negotiation' => '...',
            'neto' => 0.00,
            'porcen_tdec' => 0,
            'quote_price' => 0.00,
            'negotiation' => '...',
            'porcen_discount' => 0,
            'price_discount' => 0.00,
            'quote_number' => '...',
            'approved_number' => '...',
            'service_order_number' => 0,
            'bill_number' => '...',
            'bill_price' => 0.00,
            'bill_date' => $now->format('d/m/Y'),
            'incidence' => 0,
            'negotiation_description' => '...',
            'qc_description' => '...',
            'observations' => $input['observations'] ?? '...',
            'created_by' => $actor->name,
            'updated_by' => $actor->name,
            'managed_by' => $patient->managed_by,
            'supplier_id' => OperationsSupplierScope::resolveFromPatient($patient),
            'direct_service_registration_id' => $registration->id,
        ]);
    }

    /**
     * @param  array{model: class-string, column: string}  $config
     * @param  array{name: string, coverage: string}  $row
     */
    private static function createCatalogItem(
        array $config,
        TelemedicinePatient $patient,
        TelemedicineCase $case,
        OperationCoordinationService $coordination,
        array $row,
        User $actor,
    ): TelemedicinePatientLab|TelemedicinePatientStudy|TelemedicinePatientSpecialty {
        return $config['model']::query()->create([
            'telemedicine_patient_id' => $patient->id,
            'telemedicine_case_id' => $case->id,
            $config['column'] => $row['name'],
            'type' => $row['coverage'],
            'assigned_by' => (string) $actor->id,
            'status' => 'PENDIENTE',
            'operation_coordination_service_id' => $coordination->id,
        ]);
    }

    /**
     * Sin inventario de Diagnomóvil: el registro directo no descuenta stock.
     *
     * @param  array{name: string, coverage: string, quantity: int, duration: int|null, indications: string}  $row
     */
    private static function createMedication(
        TelemedicinePatient $patient,
        TelemedicineCase $case,
        OperationCoordinationService $coordination,
        array $row,
        int $doctorId,
    ): TelemedicinePatientMedications {
        return TelemedicinePatientMedications::query()->create([
            'telemedicine_patient_id' => $patient->id,
            'telemedicine_case_id' => $case->id,
            'telemedicine_doctor_id' => $doctorId,
            'medicine' => $row['name'],
            'indications' => $row['indications'],
            'quantity' => $row['quantity'],
            'duration' => $row['duration'],
            'is_covered' => $row['coverage'] === self::COVERED,
            'operation_inventory_id' => null,
            'status' => 'PENDIENTE',
            'operation_coordination_service_id' => $coordination->id,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<int>  $usageIds
     */
    private static function bitacoraText(OperationDirectServiceRegistration $registration, array $items, array $usageIds, User $actor): string
    {
        $lines = collect($items)
            ->map(fn (array $item): string => '- '.$item['category'].': '.$item['name'].' ('.$item['coverage'].')')
            ->implode("\n");

        $quota = $usageIds === []
            ? 'Sin consumo de cupo clínico.'
            : 'Cupo clínico consumido (registros de uso: '.implode(', ', $usageIds).').';

        return 'REGISTRO DIRECTO DE SERVICIOS #'.$registration->id.' por '.$actor->name
            .' el '.now()->format('d/m/Y h:i a').".\n"
            .'Diagnóstico / motivo: '.$registration->diagnosis."\n"
            .'Línea de servicio: '.$registration->service_line."\n"
            .$lines."\n"
            .$quota;
    }

    /**
     * @return list<array{name: string, coverage: string}>
     */
    private static function catalogRows(mixed $rows, string $category): array
    {
        $catalog = self::categories()[$category]['catalog'];
        $label = self::categories()[$category]['label'];
        $allowed = self::catalogOptions($catalog);
        $clean = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $name = trim((string) data_get($row, 'name'));

            if ($name === '') {
                continue;
            }

            if (! array_key_exists($name, $allowed)) {
                throw DirectServiceRegistrationException::field($category, "{$label}: «{$name}» no existe en el catálogo.");
            }

            $clean[] = ['name' => $name, 'coverage' => self::coverage(data_get($row, 'coverage'), $category, $label)];
        }

        self::assertNoDuplicates($clean, $category, $label);

        return $clean;
    }

    /**
     * @return list<array{name: string, coverage: string, quantity: int, duration: int|null, indications: string}>
     */
    private static function medicationRows(mixed $rows): array
    {
        $clean = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $name = trim((string) data_get($row, 'name'));

            if ($name === '') {
                continue;
            }

            $quantity = (int) data_get($row, 'quantity');
            $duration = filled(data_get($row, 'duration')) ? (int) data_get($row, 'duration') : null;
            $indications = trim((string) data_get($row, 'indications'));

            if ($quantity < 1 || $quantity > 10000) {
                throw DirectServiceRegistrationException::field('medications', "Medicamentos: indique una cantidad válida para «{$name}».");
            }

            if ($duration !== null && ($duration < 1 || $duration > 3650)) {
                throw DirectServiceRegistrationException::field('medications', "Medicamentos: la duración de «{$name}» debe estar entre 1 y 3650 días.");
            }

            if (mb_strlen($indications) < 3) {
                throw DirectServiceRegistrationException::field('medications', "Medicamentos: escriba las indicaciones de «{$name}».");
            }

            $clean[] = [
                'name' => Str::limit(mb_strtoupper($name), 250, ''),
                'coverage' => self::coverage(data_get($row, 'coverage'), 'medications', 'Medicamentos'),
                'quantity' => $quantity,
                'duration' => $duration,
                'indications' => Str::limit($indications, 2000, ''),
            ];
        }

        self::assertNoDuplicates($clean, 'medications', 'Medicamentos');

        return $clean;
    }

    /**
     * @return list<array{name: string, coverage: string}>
     */
    private static function standaloneRows(mixed $rows): array
    {
        $clean = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $name = trim((string) data_get($row, 'name'));

            if ($name === '') {
                continue;
            }

            if (! in_array($name, self::STANDALONE_SERVICES, true)) {
                throw DirectServiceRegistrationException::field('standalone', "Servicio «{$name}» no permitido.");
            }

            $clean[] = ['name' => $name, 'coverage' => self::coverage(data_get($row, 'coverage'), 'standalone', 'Servicios')];
        }

        self::assertNoDuplicates($clean, 'standalone', 'Servicios');

        return $clean;
    }

    private static function coverage(mixed $value, string $field, string $label): string
    {
        $value = mb_strtoupper(trim((string) $value));

        if (! in_array($value, [self::COVERED, self::NOT_COVERED], true)) {
            throw DirectServiceRegistrationException::field($field, "{$label}: indique si cada ítem es CUBIERTO o NO CUBIERTO.");
        }

        return $value;
    }

    /**
     * @param  list<array{name: string}>  $rows
     */
    private static function assertNoDuplicates(array $rows, string $field, string $label): void
    {
        if (count($rows) > self::MAX_ITEMS_PER_CATEGORY) {
            throw DirectServiceRegistrationException::field($field, "{$label}: máximo ".self::MAX_ITEMS_PER_CATEGORY.' ítems por registro.');
        }

        $names = array_map(fn (array $row): string => mb_strtoupper($row['name']), $rows);
        $duplicates = array_unique(array_diff_assoc($names, array_unique($names)));

        if ($duplicates !== []) {
            throw DirectServiceRegistrationException::field($field, "{$label}: «".reset($duplicates).'» está repetido.');
        }
    }

    private static function parseDate(mixed $value, string $field, string $label): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            throw DirectServiceRegistrationException::field($field, "Indique una {$label} válida.");
        }

        if (blank($value) || $date->gt(CarbonImmutable::today()->addDays(30)) || $date->lt(CarbonImmutable::today()->subYears(2))) {
            throw DirectServiceRegistrationException::field($field, "La {$label} debe estar entre hace dos años y los próximos 30 días.");
        }

        return $date;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function itemCount(array $input): int
    {
        return count($input['labs']) + count($input['studies']) + count($input['specialists'])
            + count($input['medications']) + count($input['standalone']);
    }
}
