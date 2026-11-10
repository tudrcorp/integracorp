<?php

declare(strict_types=1);

namespace App\Support\Affiliations\AffiliateFicha;

use App\Enums\AffiliateFichaScope;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliateUpgrade;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateRenovationHistory;
use App\Models\AffiliationRenovationHistory;
use App\Models\Collection as CollectionModel;
use App\Models\OperationCoordinationService;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Support\ClinicalEntitlements\AffiliateClinicalEntitlementResolver;
use App\Support\ClinicalEntitlements\ClinicalEntitlement;
use App\Support\Collections\CollectionDueDate;
use App\Support\Telemedicine\TelemedicinePatientIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Arma los datos de la ficha del afiliado (individual o corporativo) como un
 * arreglo de textos ya formateados: la plantilla solo pinta, y el arreglo sirve
 * de huella para la caché del PDF ({@see \App\Services\AffiliateFichaPdfService}).
 *
 * Cada listado trae a lo sumo {@see self::ROW_LIMIT} filas, las más recientes,
 * más el total real: un afiliado con años de historia no produce un PDF de 80
 * páginas, y la ficha dice cuántos registros quedaron fuera.
 *
 * Los pagos y avisos de cobro son de la afiliación (el grupo), no de la
 * persona: así lo registra Administración, y la ficha lo rotula así.
 */
final class AffiliateFichaData
{
    public const KIND_INDIVIDUAL = 'individual';

    public const KIND_CORPORATE = 'corporativo';

    public const ROW_LIMIT = 20;

    private const EMPTY = '—';

    /**
     * Antecedentes que la historia clínica guarda como sí/no.
     *
     * @var array<string, string>
     */
    private const HISTORY_FLAGS = [
        'tension_alta' => 'Hipertensión',
        'presion_arterial_alta' => 'Presión arterial alta',
        'diabetes' => 'Diabetes',
        'diabetes_mellitus' => 'Diabetes mellitus',
        'asma' => 'Asma',
        'cardiacos' => 'Enfermedad cardíaca',
        'gastritis_ulceras' => 'Gastritis / úlceras',
        'enfermedad_autoimmune' => 'Enfermedad autoinmune',
        'lupus' => 'Lupus',
        'tiroides' => 'Tiroides',
        'hepatitis' => 'Hepatitis',
        'cancer' => 'Cáncer',
        'vih' => 'VIH',
        'neurologia' => 'Enfermedad neurológica',
        'psiquiatricas' => 'Enfermedad psiquiátrica',
        'ansiedad_angustia' => 'Ansiedad / angustia',
        'covid' => 'COVID-19',
        'trombosis_venosa' => 'Trombosis venosa',
        'trombosis_embooleanas' => 'Trombosis / embolias',
        'alteraciones_coagulacion' => 'Alteraciones de la coagulación',
        'fracturas' => 'Fracturas',
        'tranfusiones_sanguineas' => 'Transfusiones sanguíneas',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function forIndividual(Affiliate $affiliate, AffiliateFichaScope $scope): array
    {
        $affiliate->loadMissing([
            'plan:id,code,description',
            'coverage:id,code,price',
            'country:id,name',
            'state:id,definition',
            'city:id,definition',
            'businessUnit:id,definition',
            'businessLine:id,definition',
            'affiliation' => fn ($query) => $query->withTrashed(),
            'affiliation.agent:id,name,code_agent',
            'affiliation.agency:id,code,name_corporative',
        ]);

        /** @var Affiliation|null $affiliation */
        $affiliation = $affiliate->affiliation;

        return [
            'kind' => self::KIND_INDIVIDUAL,
            'kindLabel' => 'Afiliación individual',
            'scope' => $scope->value,
            'scopeLabel' => $scope->label(),
            'name' => self::text($affiliate->full_name),
            'document' => self::text($affiliate->nro_identificacion),
            'status' => self::text($affiliate->status),
            'affiliationCode' => self::text($affiliation?->code),
            'affiliateRows' => [
                ['Nombre completo', self::text($affiliate->full_name)],
                ['Cédula / documento', self::text($affiliate->nro_identificacion)],
                ['Fecha de nacimiento', self::date($affiliate->birth_date)],
                ['Edad', self::age($affiliate->birth_date, $affiliate->age)],
                ['Sexo', self::sex($affiliate->sex)],
                ['Parentesco', self::text($affiliate->relationship)],
                ['Teléfono', self::text($affiliate->phone)],
                ['Correo', self::text($affiliate->email)],
                ['Dirección', self::text($affiliate->address)],
                ['Ubicación', self::joinText([$affiliate->city?->definition, $affiliate->state?->definition, $affiliate->country?->name])],
                ['Estatus', self::text($affiliate->status)],
                ['Registrado el', self::date($affiliate->created_at)],
                ['Plan', self::text($affiliate->plan?->description)],
                ['Cobertura', self::money($affiliate->coverage?->price)],
                ['Unidad de negocio', self::text($affiliate->businessUnit?->definition)],
                ['Línea de negocio', self::text($affiliate->businessLine?->definition)],
                ['Unidad específica', self::text($affiliate->specific_business_unit)],
                ['Tipo de afiliación', self::text($affiliate->affiliation_type)],
            ],
            'affiliationRows' => $affiliation === null ? [] : [
                ['Código', self::text($affiliation->code)],
                ['Estatus', self::text($affiliation->status)],
                ['Titular', self::text($affiliation->full_name_ti)],
                ['Documento del titular', self::joinText([$affiliation->document_type_ti, $affiliation->nro_identificacion_ti], '-')],
                ['Fecha de afiliación', self::date($affiliation->created_at)],
                ['Vigencia desde', self::date($affiliation->effective_date)],
                ['Activada el', self::date($affiliation->activated_at)],
                ['Frecuencia de pago', self::text($affiliation->payment_frequency)],
                ['Agente', self::joinText([$affiliation->agent?->name, $affiliation->agent?->code_agent], ' · ')],
                ['Agencia', self::joinText([$affiliation->agency?->name_corporative, $affiliation->agency?->code ?? $affiliation->code_agency], ' · ')],
            ],
            'administration' => $scope->includesAdministration() ? self::individualAdministration($affiliate, $affiliation) : null,
            'clinical' => $scope->includesClinical() ? self::clinical(
                document: (string) $affiliate->nro_identificacion,
                preferredPatientColumn: 'afilliation_id',
                preferredPatientValue: $affiliation?->id,
                healthRows: [
                    ['Estatura', self::text($affiliate->stature)],
                    ['Peso', self::text($affiliate->weight)],
                    ['Voucher ILS', self::ils($affiliate->vaucherIls, $affiliate->dateInit, $affiliate->dateEnd)],
                ],
                entitlements: fn () => AffiliateClinicalEntitlementResolver::forAffiliate($affiliate),
            ) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forCorporate(AffiliateCorporate $affiliate, AffiliateFichaScope $scope): array
    {
        $affiliate->loadMissing([
            'plan:id,code,description',
            'coverage:id,code,price',
            'businessUnit:id,definition',
            'businessLine:id,definition',
            'ilsVouchers',
            'affiliationCorporate' => fn ($query) => $query->withTrashed(),
            'affiliationCorporate.agent:id,name,code_agent',
            'affiliationCorporate.agency:id,code,name_corporative',
        ]);

        /** @var AffiliationCorporate|null $affiliation */
        $affiliation = $affiliate->affiliationCorporate;
        $name = trim((string) $affiliate->first_name.' '.(string) $affiliate->last_name);

        return [
            'kind' => self::KIND_CORPORATE,
            'kindLabel' => 'Afiliación corporativa',
            'scope' => $scope->value,
            'scopeLabel' => $scope->label(),
            'name' => self::text($name),
            'document' => self::text($affiliate->nro_identificacion),
            'status' => self::text($affiliate->status),
            'affiliationCode' => self::text($affiliation?->code),
            'affiliateRows' => [
                ['Nombre completo', self::text($name)],
                ['Cédula / documento', self::text($affiliate->nro_identificacion)],
                ['Fecha de nacimiento', self::date($affiliate->birth_date)],
                ['Edad', self::age($affiliate->birth_date, $affiliate->age)],
                ['Sexo', self::sex($affiliate->sex)],
                ['Parentesco', self::text($affiliate->relationship)],
                ['Teléfono', self::text($affiliate->phone)],
                ['Correo', self::text($affiliate->email)],
                ['Dirección', self::text($affiliate->address)],
                ['Cargo en la empresa', self::text($affiliate->position_company)],
                ['Contacto de emergencia', self::joinText([$affiliate->full_name_emergency, $affiliate->phone_emergency], ' · ')],
                ['Estatus', self::text($affiliate->status)],
                ['Fecha de ingreso', self::date($affiliate->initial_date ?? $affiliate->created_at)],
                ['Plan', self::text($affiliate->plan?->description)],
                ['Cobertura', self::money($affiliate->coverage?->price)],
                ['Unidad de negocio', self::text($affiliate->businessUnit?->definition)],
                ['Línea de negocio', self::text($affiliate->businessLine?->definition)],
                ['Unidad específica', self::text($affiliate->specific_business_unit)],
            ],
            'affiliationRows' => $affiliation === null ? [] : [
                ['Código', self::text($affiliation->code)],
                ['Estatus', self::text($affiliation->status)],
                ['Empresa', self::text($affiliation->name_corporate)],
                ['RIF', self::joinText([$affiliation->document_type_rif, $affiliation->rif], '-')],
                ['Fecha de afiliación', self::date($affiliation->date_affiliation ?? $affiliation->created_at)],
                ['Vigencia desde', self::date($affiliation->effective_date)],
                ['Activada el', self::date($affiliation->activated_at)],
                ['Frecuencia de pago', self::text($affiliation->payment_frequency)],
                ['Agente', self::joinText([$affiliation->agent?->name, $affiliation->agent?->code_agent], ' · ')],
                ['Agencia', self::joinText([$affiliation->agency?->name_corporative, $affiliation->agency?->code ?? $affiliation->code_agency], ' · ')],
            ],
            'administration' => $scope->includesAdministration() ? self::corporateAdministration($affiliate, $affiliation) : null,
            'clinical' => $scope->includesClinical() ? self::clinical(
                document: (string) $affiliate->nro_identificacion,
                preferredPatientColumn: 'afilliation_corporate_id',
                preferredPatientValue: $affiliation?->id,
                healthRows: [
                    ['Condición médica declarada', self::text($affiliate->condition_medical)],
                    ['Voucher ILS', self::corporateIls($affiliate)],
                ],
                entitlements: fn () => AffiliateClinicalEntitlementResolver::forAffiliateCorporate($affiliate),
            ) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function individualAdministration(Affiliate $affiliate, ?Affiliation $affiliation): array
    {
        $upgrades = AffiliateUpgrade::query()->where('affiliate_id', $affiliate->id);

        return [
            'feeRows' => [
                ['Tarifa anual del afiliado', self::money($affiliate->fee)],
                ['Total del afiliado', self::money($affiliate->total_amount)],
                ['Frecuencia de pago', self::text($affiliate->payment_frequency ?? $affiliation?->payment_frequency)],
                ['Tarifa anual de la afiliación', self::money($affiliation?->fee_anual)],
                ['Total de la afiliación', self::money($affiliation?->total_amount)],
                ['Observaciones de pago', self::text($affiliation?->observations_payment)],
            ],
            'payerRows' => $affiliation === null ? [] : [
                ['Pagador', self::text($affiliation->full_name_payer)],
                ['Documento', self::joinText([$affiliation->document_type_payer, $affiliation->nro_identificacion_payer], '-')],
                ['Parentesco con el titular', self::text($affiliation->relationship_payer)],
                ['Teléfono', self::text($affiliation->phone_payer)],
                ['Correo', self::text($affiliation->email_payer)],
            ],
            'upgrades' => self::upgradesTable($upgrades),
            ...self::collectionsAndPayments(
                code: $affiliation?->code,
                payments: $affiliation === null ? null : PaidMembership::query()->where('affiliation_id', $affiliation->id),
            ),
            'renovations' => self::renovationsTable(
                AffiliationRenovationHistory::query()->where('affiliate_id', $affiliate->id),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function corporateAdministration(AffiliateCorporate $affiliate, ?AffiliationCorporate $affiliation): array
    {
        $upgrades = AffiliateCorporateUpgrade::query()->where('affiliate_corporate_id', $affiliate->id);

        return [
            'feeRows' => [
                ['Tarifa anual del afiliado', self::money($affiliate->fee)],
                ['Subtotal anual', self::money($affiliate->subtotal_anual)],
                ['Subtotal por frecuencia', self::money($affiliate->subtotal_payment_frequency)],
                ['Frecuencia de pago', self::text($affiliate->payment_frequency ?? $affiliation?->payment_frequency)],
                ['Tarifa anual de la afiliación', self::money($affiliation?->fee_anual)],
                ['Total de la afiliación', self::money($affiliation?->total_amount)],
            ],
            'payerRows' => $affiliation === null ? [] : [
                ['Pagador (empresa)', self::text($affiliation->name_corporate)],
                ['RIF', self::joinText([$affiliation->document_type_rif, $affiliation->rif], '-')],
                ['Contacto', self::text($affiliation->full_name_contact)],
                ['Teléfono', self::text($affiliation->phone_contact ?? $affiliation->phone)],
                ['Correo', self::text($affiliation->email_contact ?? $affiliation->email)],
            ],
            'upgrades' => self::upgradesTable($upgrades),
            ...self::collectionsAndPayments(
                code: $affiliation?->code,
                payments: $affiliation === null ? null : PaidMembershipCorporate::query()->where('affiliation_corporate_id', $affiliation->id),
            ),
            'renovations' => self::renovationsTable(
                AffiliationCorporateRenovationHistory::query()->where('affiliate_corporate_id', $affiliate->id),
            ),
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, mixed>
     */
    private static function upgradesTable(Builder $query): array
    {
        $total = (clone $query)->count();
        $rows = $query->latest('id')->limit(self::ROW_LIMIT)
            ->get(['id', 'name', 'coverage_amount', 'amount', 'billing_mode', 'status', 'created_at', 'deactivated_at'])
            ->map(fn ($upgrade): array => [
                self::text($upgrade->name),
                self::money($upgrade->coverage_amount),
                self::money($upgrade->amount),
                self::text($upgrade->billing_mode),
                self::text($upgrade->status),
                self::date($upgrade->created_at),
                self::date($upgrade->deactivated_at),
            ])->all();

        return self::table(['Upgrade', 'Cobertura', 'Monto', 'Cobro', 'Estatus', 'Asignado', 'Inactivo desde'], $rows, $total, 'El afiliado no tiene upgrades.');
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|null  $payments
     * @return array{collectionsSummary: list<array{0: string, 1: string}>, collections: array<string, mixed>, payments: array<string, mixed>}
     */
    private static function collectionsAndPayments(?string $code, ?Builder $payments): array
    {
        $code = trim((string) $code);
        $collections = $code === '' ? null : CollectionModel::query()->where('affiliation_code', $code);

        $summary = [];
        $collectionRows = [];
        $collectionTotal = 0;

        if ($collections !== null) {
            $byStatus = (clone $collections)
                ->selectRaw('status, count(*) as quantity, sum(total_amount) as amount')
                ->groupBy('status')
                ->get();

            foreach ($byStatus as $row) {
                $quantity = (int) $row->quantity;
                $summary[] = [
                    self::text($row->status),
                    $quantity.($quantity === 1 ? ' aviso' : ' avisos').' · '.self::money($row->amount),
                ];
                $collectionTotal += (int) $row->quantity;
            }

            $collectionRows = (clone $collections)
                ->orderByDesc('id')
                ->limit(self::ROW_LIMIT)
                ->get(['id', 'collection_invoice_number', 'next_payment_date', 'expiration_date', 'payment_frequency', 'total_amount', 'status', 'follow_up_status'])
                ->map(fn (CollectionModel $collection): array => [
                    self::text($collection->collection_invoice_number),
                    self::date($collection->next_payment_date),
                    self::date($collection->expiration_date),
                    self::text($collection->payment_frequency),
                    self::money($collection->total_amount),
                    self::text($collection->status),
                    self::text($collection->follow_up_status),
                ])->all();
        }

        $paymentRows = [];
        $paymentTotal = 0;

        if ($payments !== null) {
            $paymentTotal = (clone $payments)->count();
            $paymentRows = $payments
                ->orderByDesc('id')
                ->limit(self::ROW_LIMIT)
                ->get(['id', 'payment_date', 'total_amount', 'pay_amount_usd', 'pay_amount_ves', 'payment_method', 'reference_payment_usd', 'reference_payment_ves', 'invoice_number', 'status'])
                ->map(fn ($payment): array => [
                    self::date($payment->payment_date),
                    self::money($payment->total_amount),
                    self::text($payment->payment_method),
                    self::joinText([$payment->reference_payment_usd, $payment->reference_payment_ves], ' / '),
                    self::text($payment->invoice_number),
                    self::text($payment->status),
                ])->all();
        }

        return [
            'collectionsSummary' => $summary,
            'collections' => self::table(['N° aviso', 'Próximo pago', 'Vence', 'Frecuencia', 'Monto', 'Estatus', 'Gestión'], $collectionRows, $collectionTotal, 'La afiliación no tiene avisos de cobro.'),
            'payments' => self::table(['Fecha', 'Monto', 'Forma de pago', 'Referencia', 'Factura', 'Estatus'], $paymentRows, $paymentTotal, 'La afiliación no tiene pagos registrados.'),
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, mixed>
     */
    private static function renovationsTable(Builder $query): array
    {
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('accepted_at')->orderByDesc('id')->limit(self::ROW_LIMIT)
            ->get(['id', 'accepted_at', 'accepted_by', 'previous_effective_date', 'new_effective_date', 'fee', 'payment_frequency', 'is_early_acceptance'])
            ->map(fn ($renovation): array => [
                self::date($renovation->accepted_at),
                self::date($renovation->previous_effective_date),
                self::date($renovation->new_effective_date),
                self::money($renovation->fee),
                self::text($renovation->payment_frequency),
                $renovation->is_early_acceptance ? 'Sí' : 'No',
                self::text($renovation->accepted_by),
            ])->all();

        return self::table(['Aceptada', 'Vigencia anterior', 'Vigencia nueva', 'Tarifa', 'Frecuencia', 'Anticipada', 'Aceptada por'], $rows, $total, 'El afiliado no tiene renovaciones registradas.');
    }

    /**
     * @param  list<array{0: string, 1: string}>  $healthRows
     * @param  \Closure(): \App\Support\ClinicalEntitlements\ClinicalEntitlementSnapshot  $entitlements
     * @return array<string, mixed>
     */
    private static function clinical(
        string $document,
        string $preferredPatientColumn,
        ?int $preferredPatientValue,
        array $healthRows,
        \Closure $entitlements,
    ): array {
        $patient = self::findPatient($document, $preferredPatientColumn, $preferredPatientValue);
        $patientId = $patient?->id;

        return [
            'linked' => $patient !== null,
            'patientRows' => $patient === null
                ? [['Paciente de telemedicina', 'Sin registro: el afiliado aún no ha sido atendido por telemedicina.']]
                : [
                    ['Código de paciente', self::text($patient->code)],
                    ['Estatus de afiliación', self::text($patient->status_affiliation)],
                    ['Teléfono de contacto', self::text($patient->phone_contact ?? $patient->phone)],
                    ['Registrado el', self::date($patient->created_at)],
                ],
            'healthRows' => $healthRows,
            'entitlements' => self::entitlementsTable($entitlements),
            'historyRows' => $patientId === null ? [] : self::historyRows($patientId),
            'cases' => $patientId === null ? null : self::casesTable($patientId),
            'consultations' => $patientId === null ? null : self::consultationsTable($patientId),
            'medications' => $patientId === null ? null : self::medicationsTable($patientId),
            'orders' => $patientId === null ? null : self::ordersTable($patientId),
            'coordinations' => self::coordinationsTable($patientId, $document),
        ];
    }

    private static function findPatient(string $document, string $preferredColumn, ?int $preferredValue): ?TelemedicinePatient
    {
        $documents = self::documentVariants($document);

        if ($documents === []) {
            return null;
        }

        return TelemedicinePatient::query()
            ->whereIn('nro_identificacion', $documents)
            ->when($preferredValue !== null, fn (Builder $query) => $query->orderByRaw("CASE WHEN {$preferredColumn} = ? THEN 0 ELSE 1 END", [$preferredValue]))
            ->orderByDesc('id')
            ->first(['id', 'code', 'status_affiliation', 'phone', 'phone_contact', 'created_at']);
    }

    /**
     * @return list<string>
     */
    private static function documentVariants(string $document): array
    {
        $raw = trim($document);
        $normalized = TelemedicinePatientIdentity::normalizeDocument($raw);

        return array_values(array_unique(array_filter([$raw, $normalized], static fn (string $value): bool => $value !== '')));
    }

    /**
     * @param  \Closure(): \App\Support\ClinicalEntitlements\ClinicalEntitlementSnapshot  $resolver
     * @return array<string, mixed>
     */
    private static function entitlementsTable(\Closure $resolver): array
    {
        try {
            $snapshot = $resolver();
        } catch (Throwable $exception) {
            Log::warning('Ficha del afiliado: no se pudieron resolver los cupos clínicos.', ['error' => $exception->getMessage()]);

            return self::table(['Servicio', 'Uso', 'Saldo'], [], 0, 'No se pudieron calcular los cupos del plan en este momento.');
        }

        if (! $snapshot->hasPlan) {
            return self::table(['Servicio', 'Uso', 'Saldo'], [], 0, $snapshot->blockingMessage !== '' ? $snapshot->blockingMessage : 'El afiliado no tiene plan asociado.');
        }

        $rows = array_map(static fn (ClinicalEntitlement $entitlement): array => [
            $entitlement->displayName(),
            $entitlement->operationsCountLabel(),
            $entitlement->operationsBalanceLabel(),
        ], array_values($snapshot->entitlements));

        return self::table(['Servicio', 'Uso', 'Saldo'], $rows, count($rows), 'El plan no tiene cupos clínicos configurados.');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function historyRows(int $patientId): array
    {
        $history = TelemedicineHistoryPatient::query()
            ->where('telemedicine_patient_id', $patientId)
            ->latest('id')
            ->first();

        if ($history === null) {
            return [['Historia clínica', 'Sin historia clínica registrada.']];
        }

        $conditions = [];

        foreach (self::HISTORY_FLAGS as $column => $label) {
            if (self::isYes($history->getAttribute($column))) {
                $conditions[] = $label;
            }
        }

        $allergies = $history->allergies;
        $allergies = is_array($allergies) ? implode(', ', array_filter(array_map('strval', $allergies))) : (string) $allergies;

        return array_values(array_filter([
            ['Historia registrada el', self::date($history->history_date ?? $history->created_at)],
            ['Antecedentes', $conditions === [] ? 'Ninguno marcado' : implode(', ', $conditions)],
            ['Alergias', self::text(trim($allergies.' '.(string) $history->observations_allergies))],
            ['Cirugías previas', self::text($history->history_surgical)],
            ['Medicamentos y suplementos', self::text(trim((string) $history->medications_supplements.' '.(string) $history->observations_medication))],
            ['Observaciones', self::text($history->observations_personal ?? $history->observations_diagnosis)],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private static function casesTable(int $patientId): array
    {
        $query = TelemedicineCase::query()->where('telemedicine_patient_id', $patientId);
        $total = (clone $query)->count();

        $rows = $query->with('telemedicineDoctor:id,full_name')
            ->latest('id')
            ->limit(self::ROW_LIMIT)
            ->get(['id', 'code', 'telemedicine_doctor_id', 'reason', 'status', 'created_at'])
            ->map(fn (TelemedicineCase $case): array => [
                self::text($case->code),
                self::date($case->created_at),
                self::text($case->reason),
                self::text($case->telemedicineDoctor?->full_name),
                self::text($case->status),
            ])->all();

        return self::table(['Caso', 'Fecha', 'Motivo', 'Médico', 'Estatus'], $rows, $total, 'Sin casos de telemedicina.');
    }

    /**
     * @return array<string, mixed>
     */
    private static function consultationsTable(int $patientId): array
    {
        $query = TelemedicineConsultationPatient::query()
            ->where('telemedicine_patient_id', $patientId)
            ->where(fn (Builder $query) => $query->whereNull('telemedicine_case_id')->orWhereHas('telemedicineCase'));
        $total = (clone $query)->count();

        $rows = $query->with(['telemedicineDoctor:id,full_name', 'telemedicineServiceList:id,name'])
            ->latest('id')
            ->limit(self::ROW_LIMIT)
            ->get(['id', 'telemedicine_case_code', 'telemedicine_doctor_id', 'telemedicine_service_list_id', 'reason_consultation', 'diagnostic_impression', 'status', 'created_at'])
            ->map(fn (TelemedicineConsultationPatient $consultation): array => [
                self::date($consultation->created_at),
                self::text($consultation->telemedicine_case_code),
                self::text($consultation->telemedicineServiceList?->name),
                self::text($consultation->reason_consultation),
                self::text($consultation->diagnostic_impression),
                self::text($consultation->telemedicineDoctor?->full_name),
            ])->all();

        return self::table(['Fecha', 'Caso', 'Servicio', 'Motivo', 'Impresión diagnóstica', 'Médico'], $rows, $total, 'Sin consultas registradas.');
    }

    /**
     * @return array<string, mixed>
     */
    private static function medicationsTable(int $patientId): array
    {
        $query = TelemedicinePatientMedications::query()->where('telemedicine_patient_id', $patientId);
        $total = (clone $query)->count();

        $rows = $query->latest('id')
            ->limit(self::ROW_LIMIT)
            ->get(['id', 'medicine', 'indications', 'duration', 'quantity', 'status', 'created_at'])
            ->map(fn ($medication): array => [
                self::date($medication->created_at),
                self::text($medication->medicine),
                self::text($medication->indications),
                self::joinText([$medication->quantity, $medication->duration], ' · '),
                self::text($medication->status),
            ])->all();

        return self::table(['Fecha', 'Medicamento', 'Indicaciones', 'Cantidad · duración', 'Estatus'], $rows, $total, 'Sin medicamentos indicados.');
    }

    /**
     * Laboratorios, estudios de imagen y especialistas indicados, juntos y en
     * orden de fecha: es lo que Operaciones coordina a partir de la consulta.
     *
     * @return array<string, mixed>
     */
    private static function ordersTable(int $patientId): array
    {
        $sources = [
            'Laboratorio' => [TelemedicinePatientLab::class, 'laboratory'],
            'Estudio' => [TelemedicinePatientStudy::class, 'study'],
            'Especialista' => [TelemedicinePatientSpecialty::class, 'specialty'],
        ];

        $total = 0;
        $items = [];

        foreach ($sources as $label => [$model, $column]) {
            $query = $model::query()->where('telemedicine_patient_id', $patientId);
            $total += (clone $query)->count();

            foreach ($query->latest('id')->limit(self::ROW_LIMIT)->get(['id', $column, 'type', 'status', 'created_at']) as $item) {
                $items[] = [
                    'at' => $item->created_at?->getTimestamp() ?? 0,
                    'row' => [
                        self::date($item->created_at),
                        $label,
                        self::text($item->getAttribute($column)),
                        self::text($item->type),
                        self::text($item->status),
                    ],
                ];
            }
        }

        usort($items, static fn (array $left, array $right): int => $right['at'] <=> $left['at']);

        $rows = array_column(array_slice($items, 0, self::ROW_LIMIT), 'row');

        return self::table(['Fecha', 'Tipo', 'Indicación', 'Detalle', 'Estatus'], $rows, $total, 'Sin laboratorios, estudios ni especialistas indicados.');
    }

    /**
     * @return array<string, mixed>
     */
    private static function coordinationsTable(?int $patientId, string $document): array
    {
        $documents = self::documentVariants($document);

        if ($patientId === null && $documents === []) {
            return self::table([], [], 0, 'Sin servicios coordinados.');
        }

        $query = OperationCoordinationService::query()->where(function (Builder $query) use ($patientId, $documents): void {
            if ($patientId !== null) {
                $query->where('telemedicine_patient_id', $patientId);
            }

            if ($documents !== []) {
                $query->orWhereIn('ci_patient', $documents);
            }
        });
        $total = (clone $query)->count();

        $rows = $query->latest('id')
            ->limit(self::ROW_LIMIT)
            ->get(['id', 'date_solicitud', 'date_service', 'reference_number', 'servicie', 'specific_service', 'supplier_service', 'status', 'created_at'])
            ->map(fn (OperationCoordinationService $service): array => [
                self::date($service->date_service ?? $service->date_solicitud ?? $service->created_at),
                self::text($service->reference_number),
                self::joinText([$service->servicie, $service->specific_service], ' · '),
                self::text($service->supplier_service),
                self::text($service->status),
            ])->all();

        return self::table(['Fecha', 'Referencia', 'Servicio', 'Proveedor', 'Estatus'], $rows, $total, 'Sin servicios coordinados.');
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     * @return array{columns: list<string>, rows: list<list<string>>, total: int, hidden: int, empty: string}
     */
    private static function table(array $columns, array $rows, int $total, string $empty): array
    {
        return [
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
            'hidden' => max(0, $total - count($rows)),
            'empty' => $empty,
        ];
    }

    private static function ils(mixed $voucher, mixed $from, mixed $to): string
    {
        if (blank($voucher)) {
            return 'Sin voucher';
        }

        return self::joinText([(string) $voucher, self::range($from, $to)], ' · ');
    }

    private static function corporateIls(AffiliateCorporate $affiliate): string
    {
        $latest = $affiliate->ilsVouchers->sortByDesc('id')->first();

        if ($latest !== null && filled($latest->voucher_code)) {
            return self::ils($latest->voucher_code, $latest->date_init, $latest->date_end);
        }

        return self::ils($affiliate->vaucherIls, $affiliate->dateInit, $affiliate->dateEnd);
    }

    private static function range(mixed $from, mixed $to): string
    {
        $fromText = self::date($from);
        $toText = self::date($to);

        if ($fromText === self::EMPTY && $toText === self::EMPTY) {
            return '';
        }

        return 'del '.$fromText.' al '.$toText;
    }

    private static function isYes(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(mb_strtoupper(trim((string) $value)), ['SI', 'SÍ', 'YES', 'TRUE'], true);
    }

    public static function text(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter(array_map(static fn ($item): string => is_scalar($item) ? (string) $item : '', $value)));
        }

        $text = trim(is_scalar($value) ? (string) $value : '');

        return $text === '' ? self::EMPTY : $text;
    }

    /**
     * @param  list<mixed>  $parts
     */
    private static function joinText(array $parts, string $glue = ', '): string
    {
        $clean = array_values(array_filter(
            array_map(static fn ($part): string => trim(is_scalar($part) ? (string) $part : ''), $parts),
            static fn (string $part): bool => $part !== '',
        ));

        return $clean === [] ? self::EMPTY : implode($glue, $clean);
    }

    public static function money(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return self::EMPTY;
        }

        return 'US$ '.number_format((float) $value, 2, ',', '.');
    }

    public static function date(mixed $value): string
    {
        $date = self::parseDate($value);

        return $date === null ? self::text($value) : $date->format('d/m/Y');
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        $text = trim(is_scalar($value) ? (string) $value : '');

        if ($text === '') {
            return null;
        }

        $date = CollectionDueDate::parse($text);

        if ($date !== null) {
            return $date;
        }

        if (preg_match('#^\d{4}-\d{2}-\d{2}#', $text) === 1) {
            try {
                return CarbonImmutable::parse($text);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private static function age(mixed $birthDate, mixed $storedAge): string
    {
        $birth = self::parseDate($birthDate);

        if ($birth !== null && $birth->isPast()) {
            return $birth->age.' años';
        }

        return is_numeric($storedAge) ? (int) $storedAge.' años' : self::EMPTY;
    }

    private static function sex(mixed $value): string
    {
        return match (mb_strtoupper(trim((string) $value))) {
            'M', 'MASCULINO' => 'Masculino',
            'F', 'FEMENINO' => 'Femenino',
            default => self::text($value),
        };
    }
}
