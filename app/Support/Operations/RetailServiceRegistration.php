<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineListLaboratory;
use App\Models\TelemedicineListSpecialist;
use App\Models\TelemedicineListStudy;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Telemedicine\TelemedicineCaseFactory;
use App\Support\Telemedicine\TelemedicineCaseIdentity;
use App\Support\Telemedicine\TelemedicineCoverageCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registro de servicios TPA/RETAIL desde la ficha del paciente en Operaciones.
 *
 * Cada servicio (principal o categoría de ítems) es una coordinación con su
 * proveedor asignado: natural, jurídico o aliado corporativo. El proveedor
 * queda **asignado**, no gestionado: la coordinación nace PENDIENTE y sigue el
 * camino normal de Coordinación de Servicios, con el proveedor ya propuesto.
 *
 * Si el retail incluye TELEMEDICINA o AMD y su proveedor es un equipo médico
 * (TDG o un proveedor de gestión con médicos), el caso se asigna a ese equipo y
 * lo atiende el médico de guardia desde el panel de Telemedicina. Si no, el caso
 * queda en estatus RETAIL, solo para Operaciones.
 *
 * Retail no consume cupo clínico: nada pasa por {@see \App\Support\ClinicalEntitlements\ClinicalUsageLedger}.
 *
 * Todo ocurre en una transacción: si un paso falla no queda nada a medias.
 */
final class RetailServiceRegistration
{
    public const SERVICIE = 'TPA/RETAIL';

    public const CASE_STATUS = 'RETAIL';

    public const TEAM_CASE_STATUS = 'ASIGNADO';

    public const CASE_REASON = 'SERVICIOS TPA/RETAIL';

    public const COVERED = 'CUBIERTO';

    public const NOT_COVERED = 'NO CUBIERTO';

    public const MAX_ITEMS_PER_CATEGORY = 50;

    /**
     * Categorías con ítems. `specific_service` es el tipo de servicio de la coordinación.
     *
     * @return array<string, array{label: string, specific_service: string, model: class-string, column: string, catalog: class-string|null}>
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
            ],
            'studies' => [
                'label' => 'Estudios',
                'specific_service' => 'IMAGENOLOGIA',
                'model' => TelemedicinePatientStudy::class,
                'column' => 'study',
                'catalog' => TelemedicineListStudy::class,
            ],
            'specialists' => [
                'label' => 'Consultas con especialista',
                'specific_service' => 'ESPECIALISTA',
                'model' => TelemedicinePatientSpecialty::class,
                'column' => 'specialty',
                'catalog' => TelemedicineListSpecialist::class,
            ],
            'medications' => [
                'label' => 'Medicamentos',
                'specific_service' => 'MEDICAMENTOS',
                'model' => TelemedicinePatientMedications::class,
                'column' => 'medicine',
                'catalog' => null,
            ],
        ];
    }

    /**
     * Servicios principales (sin catálogo de ítems).
     *
     * @return list<string>
     */
    public static function mainServices(): array
    {
        return RegisterTpaRetailServicesAction::standaloneSpecificServices();
    }

    /**
     * Clave estable del campo de proveedor de un servicio principal
     * (los nombres llevan espacios y paréntesis).
     */
    public static function serviceFieldKey(string $service): string
    {
        return Str::slug($service, '_');
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

    public static function suggestedCoverage(string $category, string $name): string
    {
        $covered = match ($category) {
            'labs' => TelemedicineCoverageCatalog::laboratoryIsCovered($name),
            'studies' => TelemedicineCoverageCatalog::studyIsCovered($name),
            'specialists' => TelemedicineCoverageCatalog::specialistIsCovered($name),
            default => false,
        };

        return $covered ? self::COVERED : self::NOT_COVERED;
    }

    /**
     * Valida y limpia lo que llega del formulario. Los mensajes van tal cual al analista.
     *
     * @param  array<string, mixed>  $data
     * @return array{services: list<array{name: string, field: string, provider: array<string, mixed>}>, categories: array<string, array{rows: list<array<string, mixed>>, provider: array<string, mixed>}>, team: array{key: string, supplier_id: int|null, managed_by: string, label: string}|null, reason: string|null}
     *
     * @throws RetailServiceRegistrationException
     */
    public static function normalize(array $data): array
    {
        $services = [];
        $selectedServices = collect(is_array($data['services'] ?? null) ? $data['services'] : [])
            ->map(fn (mixed $service): string => trim((string) $service))
            ->filter()
            ->unique()
            ->values();

        foreach ($selectedServices as $service) {
            if (! in_array($service, self::mainServices(), true)) {
                throw RetailServiceRegistrationException::field('services', "El servicio «{$service}» no está permitido.");
            }

            $field = 'service_providers.'.self::serviceFieldKey($service);
            $services[] = [
                'name' => $service,
                'field' => $field,
                'provider' => self::provider(data_get($data, $field), $service, $field, $service),
            ];
        }

        $categories = [];

        foreach (self::categories() as $key => $config) {
            $rows = $key === 'medications'
                ? self::medicationRows($data['medications'] ?? [])
                : self::catalogRows($data[$key] ?? [], $key);

            if ($rows === []) {
                continue;
            }

            if (count($rows) > self::MAX_ITEMS_PER_CATEGORY) {
                throw RetailServiceRegistrationException::field($key, "{$config['label']}: máximo ".self::MAX_ITEMS_PER_CATEGORY.' ítems por registro.');
            }

            $field = $key.'_provider';
            $categories[$key] = [
                'rows' => $rows,
                'provider' => self::provider($data[$field] ?? null, $config['specific_service'], $field, $config['label']),
            ];
        }

        if ($services === [] && $categories === []) {
            throw RetailServiceRegistrationException::field('services', 'Seleccione al menos un servicio principal, laboratorio, estudio, consulta con especialista o medicamento.');
        }

        $team = null;

        foreach ($services as $service) {
            $serviceTeam = $service['provider']['team'];

            if ($serviceTeam === null) {
                continue;
            }

            if ($team !== null && $team['key'] !== $serviceTeam['key']) {
                throw RetailServiceRegistrationException::field(
                    $service['field'],
                    'Telemedicina y AMD deben quedar con el mismo equipo médico: el caso solo puede estar en la bandeja de un equipo.',
                );
            }

            $team = $serviceTeam;
        }

        $reason = Str::squish((string) ($data['reason'] ?? ''));

        if ($team !== null && mb_strlen($reason) < 5) {
            throw RetailServiceRegistrationException::field('reason', 'Escriba el motivo de la atención: es lo primero que lee el médico de guardia.');
        }

        return [
            'services' => $services,
            'categories' => $categories,
            'team' => $team,
            'reason' => $reason !== '' ? Str::limit($reason, 2000, '') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{case: TelemedicineCase, coordination_ids: list<int>, team: array{key: string, supplier_id: int|null, managed_by: string, label: string}|null}
     *
     * @throws RetailServiceRegistrationException
     */
    public static function register(TelemedicinePatient $patient, array $data, User $actor): array
    {
        $input = self::normalize($data);

        return DB::transaction(function () use ($patient, $input, $actor): array {
            $patient = OperationsSupplierScope::applyToQuery(TelemedicinePatient::query(), 'supplier_id')
                ->whereKey($patient->getKey())
                ->lockForUpdate()
                ->first();

            if (! $patient instanceof TelemedicinePatient) {
                throw new RetailServiceRegistrationException('El paciente no existe o no está en su alcance.');
            }

            $case = self::createCase($patient, $input, $actor);
            $coordinationIds = [];
            $trace = [];

            foreach ($input['services'] as $service) {
                $coordination = self::createCoordination($patient, $case, $service['name'], $service['provider'], $input['reason'], $actor);
                $coordinationIds[] = $coordination->id;

                TelemedicinePatientSpecialty::query()->create([
                    'telemedicine_patient_id' => $patient->id,
                    'telemedicine_case_id' => $case->id,
                    'type' => self::NOT_COVERED,
                    'specialty' => $service['name'],
                    'assigned_by' => (string) $actor->id,
                    'status' => 'PENDIENTE',
                    'operation_coordination_service_id' => $coordination->id,
                ]);

                $trace[] = '- '.$service['name'].' → '.self::providerTraceLabel($service['provider']);
            }

            foreach ($input['categories'] as $key => $category) {
                $config = self::categories()[$key];
                $coordination = self::createCoordination($patient, $case, $config['specific_service'], $category['provider'], $input['reason'], $actor);
                $coordinationIds[] = $coordination->id;

                foreach ($category['rows'] as $row) {
                    $key === 'medications'
                        ? self::createMedication($patient, $case, $coordination, $row)
                        : self::createCatalogItem($config, $patient, $case, $coordination, $row, $actor);
                }

                $trace[] = '- '.$config['label'].' ('.collect($category['rows'])->pluck('name')->implode(', ').') → '.self::providerTraceLabel($category['provider']);
            }

            ObservationCase::query()->create([
                'telemedicine_case_id' => $case->id,
                'description' => self::bitacoraText($input, $trace, $actor),
                'created_by' => (string) $actor->id,
            ]);

            return ['case' => $case, 'coordination_ids' => $coordinationIds, 'team' => $input['team']];
        });
    }

    /**
     * @param  array{team: array{supplier_id: int|null, managed_by: string}|null, reason: string|null}  $input
     */
    private static function createCase(TelemedicinePatient $patient, array $input, User $actor): TelemedicineCase
    {
        $team = $input['team'];
        $reason = self::CASE_REASON.($input['reason'] !== null ? ': '.$input['reason'] : '');

        $assignment = $team === null
            ? ['status' => self::CASE_STATUS, 'managed_by' => $patient->managed_by]
            : [
                'status' => self::TEAM_CASE_STATUS,
                'telemedicine_doctor_id' => null,
                'assigned_to_medical_team' => true,
                'medical_team_supplier_id' => $team['supplier_id'],
                'managed_by' => $team['managed_by'],
                'belongs_to' => null,
            ];

        return TelemedicineCaseFactory::createForPatient($patient, [
            ...$assignment,
            'reason' => Str::limit($reason, 2000, ''),
            'assigned_by' => $actor->name,
            'supplier_id' => OperationsSupplierScope::resolveFromPatient($patient),
        ]);
    }

    /**
     * @param  array{type: string, supplier_id: int|null, doctor_nurse_id: int|null, corporate_ally_id: int|null, name: string, team: array{supplier_id: int|null, managed_by: string, label: string}|null}  $provider
     */
    private static function createCoordination(
        TelemedicinePatient $patient,
        TelemedicineCase $case,
        string $specificService,
        array $provider,
        ?string $reason,
        User $actor,
    ): OperationCoordinationService {
        $identity = TelemedicineCaseIdentity::coordinationIdentity([], $patient);

        return OperationCoordinationService::query()->create([
            'telemedicine_patient_id' => $patient->id,
            'telemedicine_case_id' => $case->id,
            'date_solicitud' => now(),
            'date_service' => now(),
            'business_line_id' => $patient->business_line_id,
            'business_unit_id' => $patient->business_unit_id,
            'reference_number' => (string) $case->code,
            'status' => 'PENDIENTE',
            'patient' => $identity['patient'],
            'ci_patient' => $identity['ci_patient'] ?? '...',
            'birth_date_patient' => $identity['birth_date_patient'],
            'relationship_patient' => $identity['relationship_patient'],
            'age_patient' => $identity['age_patient'],
            'contractor' => $patient->afilliation_id === null ? 'CORPORATIVO' : 'INDIVIDUAL',
            'state_id' => $patient->state_id,
            'city_id' => $patient->city_id,
            'address' => $patient->address,
            'phone_holder' => $patient->phone,
            'symptoms_diagnosis' => $reason ?? 'SERVICIO TPA/RETAIL',
            'servicie' => self::SERVICIE,
            'specific_service' => $specificService,
            'supplier_service' => Str::limit($provider['name'], 250, ''),
            'assigned_provider_type' => $provider['type'],
            'assigned_supplier_id' => $provider['supplier_id'],
            'assigned_doctor_nurse_id' => $provider['doctor_nurse_id'],
            'assigned_corporate_ally_id' => $provider['corporate_ally_id'],
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
            'bill_date' => now()->format('d/m/Y'),
            'incidence' => 0,
            'negotiation_description' => '...',
            'qc_description' => '...',
            'created_by' => $actor->name,
            'updated_by' => $actor->name,
            ...self::management($patient, $provider['team'] ?? null, $actor),
        ]);
    }

    /**
     * Quién gestiona la coordinación en Operaciones.
     *
     * Sin equipo médico, la gestión del paciente, como siempre. Con equipo
     * (solo Telemedicina y AMD), la del equipo, igual que una coordinación del
     * flujo médico toma la del médico: así la columna «Gestionado por» coincide
     * con el caso. Si el equipo es de un proveedor, además queda asignada por TDG
     * a ese proveedor ({@see AssignCoordinationServiceToSupplier}) para que sus
     * analistas la vean en su portal aunque el servicio sea no cubierto.
     *
     * @param  array{supplier_id: int|null, managed_by: string, label: string}|null  $team
     * @return array<string, mixed>
     */
    private static function management(TelemedicinePatient $patient, ?array $team, User $actor): array
    {
        if ($team === null) {
            return [
                'observations' => '...',
                'managed_by' => $patient->managed_by,
                'supplier_id' => OperationsSupplierScope::resolveFromPatient($patient),
            ];
        }

        if ($team['supplier_id'] === null) {
            return [
                'observations' => '...',
                'managed_by' => $team['managed_by'],
                'supplier_id' => null,
            ];
        }

        $supplier = Supplier::query()->findOrFail($team['supplier_id']);

        return [
            'observations' => AssignCoordinationServiceToSupplier::buildBitacoraDescription(
                $supplier,
                'Registro TPA/RETAIL: servicio asignado al '.$team['label'].'.',
            ),
            'managed_by' => $team['managed_by'],
            'supplier_id' => $supplier->id,
            'assigned_to_supplier_by_tdg' => true,
            'assigned_to_supplier_by_tdg_at' => now(),
            'assigned_to_supplier_by_tdg_by' => $actor->name,
        ];
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
    ): void {
        $config['model']::query()->create([
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
     * Sin médico particular (lo indica el equipo del caso, o nadie si el retail no
     * lleva telemedicina ni AMD) y sin inventario Diagnomóvil: retail no descuenta stock.
     *
     * @param  array{name: string, quantity: int, duration: int|null, indications: string}  $row
     */
    private static function createMedication(
        TelemedicinePatient $patient,
        TelemedicineCase $case,
        OperationCoordinationService $coordination,
        array $row,
    ): void {
        TelemedicinePatientMedications::query()->create([
            'telemedicine_patient_id' => $patient->id,
            'telemedicine_case_id' => $case->id,
            'telemedicine_doctor_id' => null,
            'medicine' => $row['name'],
            'indications' => $row['indications'],
            'quantity' => $row['quantity'],
            'duration' => $row['duration'],
            'is_covered' => false,
            'operation_inventory_id' => null,
            'status' => 'PENDIENTE',
            'operation_coordination_service_id' => $coordination->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RetailServiceRegistrationException
     */
    private static function provider(mixed $key, string $specificService, string $field, string $label): array
    {
        if (! filled($key)) {
            throw RetailServiceRegistrationException::field($field, "Seleccione el proveedor de «{$label}».");
        }

        $provider = RetailServiceProviderCatalog::resolve($key, $specificService);

        if ($provider === null) {
            throw RetailServiceRegistrationException::field($field, "El proveedor elegido para «{$label}» ya no está disponible. Elija otro.");
        }

        return $provider;
    }

    /**
     * @return list<array{name: string, coverage: string}>
     */
    private static function catalogRows(mixed $values, string $category): array
    {
        $config = self::categories()[$category];
        $allowed = self::catalogOptions($config['catalog']);
        $rows = [];

        foreach (collect(is_array($values) ? $values : [])->map(fn (mixed $value): string => trim((string) $value))->filter()->unique() as $name) {
            if (! array_key_exists($name, $allowed)) {
                throw RetailServiceRegistrationException::field($category, "{$config['label']}: «{$name}» no existe en el catálogo.");
            }

            $rows[] = ['name' => $name, 'coverage' => self::suggestedCoverage($category, $name)];
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, quantity: int, duration: int|null, indications: string}>
     */
    private static function medicationRows(mixed $rows): array
    {
        $clean = [];
        $seen = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $name = Str::squish((string) data_get($row, 'name'));

            if ($name === '') {
                continue;
            }

            $name = Str::limit(mb_strtoupper($name), 250, '');
            $quantity = filter_var(data_get($row, 'quantity'), FILTER_VALIDATE_INT);
            $durationRaw = data_get($row, 'duration');
            $duration = filled($durationRaw) ? filter_var($durationRaw, FILTER_VALIDATE_INT) : null;
            $indications = trim((string) data_get($row, 'indications'));

            if ($quantity === false || $quantity < 1 || $quantity > 10000) {
                throw RetailServiceRegistrationException::field('medications', "Medicamentos: indique una cantidad válida para «{$name}».");
            }

            if ($duration === false || ($duration !== null && ($duration < 1 || $duration > 3650))) {
                throw RetailServiceRegistrationException::field('medications', "Medicamentos: la duración de «{$name}» debe estar entre 1 y 3650 días.");
            }

            if (mb_strlen($indications) < 3) {
                throw RetailServiceRegistrationException::field('medications', "Medicamentos: escriba las indicaciones de «{$name}».");
            }

            if (isset($seen[$name])) {
                throw RetailServiceRegistrationException::field('medications', "Medicamentos: «{$name}» está repetido.");
            }

            $seen[$name] = true;
            $clean[] = [
                'name' => $name,
                'quantity' => $quantity,
                'duration' => $duration,
                'indications' => Str::limit($indications, 2000, ''),
            ];
        }

        return $clean;
    }

    /**
     * @param  array{type: string, name: string, team: array{label: string}|null}  $provider
     */
    private static function providerTraceLabel(array $provider): string
    {
        $label = RetailServiceProviderCatalog::typeLabel($provider['type']).': '.$provider['name'];

        return $provider['team'] !== null ? $label.' (asignado al '.$provider['team']['label'].')' : $label;
    }

    /**
     * @param  array{team: array{label: string}|null, reason: string|null}  $input
     * @param  list<string>  $trace
     */
    private static function bitacoraText(array $input, array $trace, User $actor): string
    {
        $destination = $input['team'] !== null
            ? 'Caso asignado al '.$input['team']['label'].': lo toma el médico de guardia en Telemedicina.'
            : 'Caso solo de Operaciones (sin telemedicina ni AMD con equipo médico).';

        return 'REGISTRO DE SERVICIOS TPA/RETAIL por '.$actor->name.' el '.now()->format('d/m/Y h:i a').".\n"
            .($input['reason'] !== null ? 'Motivo: '.$input['reason']."\n" : '')
            .implode("\n", $trace)."\n"
            .$destination."\n"
            .'Sin consumo de cupo clínico.';
    }
}
