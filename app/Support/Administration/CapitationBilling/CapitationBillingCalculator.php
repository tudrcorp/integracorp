<?php

declare(strict_types=1);

namespace App\Support\Administration\CapitationBilling;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliateStatusMovement;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\CapitationBillingClosing;
use App\Models\CapitationBillingClosingItem;
use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Models\Plan;
use App\Models\Supplier;
use App\Support\Affiliations\AffiliationDocumentPrefix;
use App\Support\Business\CapitationPlans\AffiliateCapitationCost;
use App\Support\Business\CapitationPlans\CapitationCostIndex;
use App\Support\Business\CapitationPlans\CapitationCostResult;
use App\Support\Business\CapitationPlans\CapitationSubject;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Calcula en vivo la pre-facturación de capitado de un proveedor Outsourcing en un mes.
 *
 * Reglas (las mismas del modelo de pre-facturación de Atenmedi):
 *
 * - **Afiliados del mes**: los activos hoy cuya afiliación lleva al proveedor y a quienes le
 *   aplica un plan de capitado de ese proveedor vigente al cierre del mes (u hoy, si el mes
 *   está en curso), dados de alta a más tardar el día 1 del mes.
 *   Cada uno paga la tarifa mensual completa de la columna «Capitado por afiliado» (plan o
 *   ajuste negociado), vía {@see AffiliateCapitationCost}, la única fuente de la tarifa.
 * - **Prorrateo de nuevos afiliados**: los del mes que entraron durante el mes anterior
 *   (después del día 1) pagan además los días de ese mes: tarifa ÷ 30 × (fecha de corte −
 *   fecha de alta), con la fecha de corte en el último día del mes anterior. Si el mes
 *   anterior está cerrado y el afiliado ya figuraba en él, no se prorratea de nuevo.
 * - **Retiros**: los que estaban en el cierre del mes anterior y ya no están en este. Sin
 *   cierre anterior, salen de la bitácora de bajas de ese mes (que existe desde el 05/11/2026).
 *
 * Los dados de alta durante el mes del reporte no entran todavía: se prorratean el mes siguiente.
 */
final class CapitationBillingCalculator
{
    public const DAYS_PER_MONTH = 30;

    private const CHUNK = 500;

    /** @var array<int, string> */
    private array $planNames = [];

    /** @var array<string, int> */
    private array $skipped = [];

    private function __construct(
        private readonly Supplier $supplier,
        private readonly string $alias,
        private readonly CarbonImmutable $period,
        private readonly CarbonImmutable $rateDate,
    ) {}

    /**
     * @param  list<array{concept: string, amount: float}>  $discounts
     */
    public static function compute(Supplier $supplier, CarbonImmutable $period, array $discounts = [], ?float $bcvRate = null, ?CarbonImmutable $now = null): CapitationBillingSnapshot
    {
        $period = $period->startOfMonth();
        $alias = CapitationCostIndex::normalize((string) $supplier->integracorp_alias);
        $now ??= CarbonImmutable::now();
        $calculator = new self($supplier, $alias, $period, self::rateDate($period, $now));

        AffiliateCapitationCost::flush();

        $roster = $alias === '' ? [] : [
            ...$calculator->individuals(),
            ...$calculator->corporates(),
            ...$calculator->associates(),
        ];

        $previous = CapitationBillingClosing::query()
            ->where('supplier_id', $supplier->getKey())
            ->where('period', $period->subMonthNoOverflow()->toDateString())
            ->where('status', CapitationBillingClosing::STATUS_CLOSED)
            ->first();

        [$monthRows, $prorateRows] = $calculator->split($roster, $previous);
        $retirementRows = $calculator->retirements($monthRows, $previous);

        return new CapitationBillingSnapshot(
            supplierId: (int) $supplier->getKey(),
            supplierName: (string) $supplier->name,
            period: $period,
            monthRows: self::sorted($monthRows),
            prorateRows: self::sorted($prorateRows),
            retirementRows: self::sorted($retirementRows),
            discounts: $discounts,
            bcvRate: $bcvRate,
            notes: $calculator->notes($alias, $previous),
            closing: null,
            generatedAt: $now,
        );
    }

    /**
     * Fecha con la que se decide si un plan de capitado está vigente: el cierre del mes o,
     * si el mes está en curso, hoy.
     */
    public static function rateDate(CarbonImmutable $period, CarbonImmutable $now): CarbonImmutable
    {
        $end = $period->endOfMonth()->startOfDay();

        return $now->startOfDay()->lessThan($end) ? $now->startOfDay() : $end;
    }

    /**
     * Días y monto del prorrateo: (fecha de corte − fecha de alta) días a tarifa ÷ 30.
     *
     * @return array{days: int, amount: float}
     */
    public static function prorate(float $monthlyRate, CarbonImmutable $enrolledOn, CarbonImmutable $cutoff): array
    {
        $days = max(0, (int) $enrolledOn->startOfDay()->diffInDays($cutoff->startOfDay(), false));

        return ['days' => $days, 'amount' => round($monthlyRate / self::DAYS_PER_MONTH * $days, 2)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function individuals(): array
    {
        $rows = [];

        Affiliate::query()
            ->whereIn('affiliation_id', Affiliation::query()->where('service_providers', 'like', '%'.$this->alias.'%')->select('id'))
            ->with(['affiliation', 'affiliation.plan:id,description', 'plan:id,description'])
            ->chunkById(self::CHUNK, function ($affiliates) use (&$rows): void {
                foreach ($affiliates as $affiliate) {
                    $affiliation = $affiliate->affiliation;

                    if (! $affiliation instanceof Affiliation || ! $this->linksSupplier($affiliation->getAttribute('service_providers'))) {
                        continue;
                    }

                    $line = $this->line(AffiliateCapitationCost::forAffiliate($affiliate, $affiliation, on: $this->rateDate));

                    if ($line === null) {
                        continue;
                    }

                    $payerType = AffiliationDocumentPrefix::forPayer($affiliation->getAttribute('document_type_payer'), $affiliation->nro_identificacion_payer, $affiliation->full_name_payer);

                    $rows[] = $this->row(
                        subjectType: CapitationSubject::AFFILIATE,
                        subjectId: (int) $affiliate->getKey(),
                        group: 'TDEC - INDIVIDUAL - '.$line['plan'],
                        holderDocumentType: $payerType->value,
                        holderDocument: $affiliation->nro_identificacion_payer,
                        name: (string) $affiliate->full_name,
                        document: $affiliate->nro_identificacion,
                        sex: $affiliate->sex,
                        birthDate: $affiliate->birth_date,
                        relationship: $affiliate->relationship,
                        line: $line,
                        tdecPlan: $affiliate->plan?->description ?? $affiliation->plan?->description,
                        enrolledOn: self::latest(self::date($affiliate->created_at), self::date($affiliation->activated_at)),
                    );
                }
            });

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function corporates(): array
    {
        $rows = [];

        AffiliateCorporate::query()
            ->whereIn('affiliation_corporate_id', AffiliationCorporate::query()->where('service_providers', 'like', '%'.$this->alias.'%')->select('id'))
            ->with(['affiliationCorporate', 'plan:id,description'])
            ->chunkById(self::CHUNK, function ($affiliates) use (&$rows): void {
                foreach ($affiliates as $affiliate) {
                    $affiliation = $affiliate->affiliationCorporate;

                    if (! $affiliation instanceof AffiliationCorporate || ! $this->linksSupplier($affiliation->getAttribute('service_providers'))) {
                        continue;
                    }

                    $line = $this->line(AffiliateCapitationCost::forAffiliateCorporate($affiliate, $affiliation, on: $this->rateDate));

                    if ($line === null) {
                        continue;
                    }

                    $rows[] = $this->row(
                        subjectType: CapitationSubject::AFFILIATE_CORPORATE,
                        subjectId: (int) $affiliate->getKey(),
                        group: self::clean($affiliation->name_corporate) ?? 'CORPORATIVA '.$affiliation->code,
                        holderDocumentType: AffiliationDocumentPrefix::forCompany($affiliation->getAttribute('document_type_rif'))->value,
                        holderDocument: $affiliation->rif,
                        name: trim($affiliate->first_name.' '.$affiliate->last_name),
                        document: $affiliate->nro_identificacion,
                        sex: $affiliate->sex,
                        birthDate: $affiliate->birth_date,
                        relationship: $affiliate->relationship,
                        line: $line,
                        tdecPlan: $affiliate->plan?->description,
                        enrolledOn: self::latest(self::date($affiliate->created_at), self::date($affiliation->activated_at)),
                    );
                }
            });

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function associates(): array
    {
        $rows = [];

        CompanyAssociate::query()
            ->whereIn('company_id', Company::query()->where('service_providers', 'like', '%'.$this->alias.'%')->select('id'))
            ->with(['company', 'company.planGenerator:id,plan_id,catalog_plan_id'])
            ->chunkById(self::CHUNK, function ($associates) use (&$rows): void {
                foreach ($associates as $associate) {
                    $company = $associate->company;

                    if (! $company instanceof Company || ! $this->linksSupplier($company->getAttribute('service_providers'))) {
                        continue;
                    }

                    $line = $this->line(AffiliateCapitationCost::forCompanyAssociate($associate, on: $this->rateDate));

                    if ($line === null) {
                        continue;
                    }

                    $generator = $company->planGenerator;
                    $planId = (int) ($generator?->catalog_plan_id ?? $generator?->plan_id ?? 0);

                    $rows[] = $this->row(
                        subjectType: CapitationSubject::COMPANY_ASSOCIATE,
                        subjectId: (int) $associate->getKey(),
                        group: self::clean($company->name) ?? 'NUEVOS NEGOCIOS',
                        holderDocumentType: AffiliationDocumentPrefix::forCompany(null)->value,
                        holderDocument: $company->rif,
                        name: (string) $associate->full_name,
                        document: $associate->identity_card,
                        sex: $associate->sex,
                        birthDate: $associate->birth_date,
                        relationship: 'TITULAR',
                        line: $line,
                        tdecPlan: $this->planName($planId),
                        enrolledOn: self::date($associate->registered_at) ?? self::date($associate->created_at),
                    );
                }
            });

        return $rows;
    }

    /**
     * Separa los afiliados del mes (alta a más tardar el día 1) y los que se prorratean
     * (alta durante el mes anterior, después del día 1).
     *
     * @param  list<array<string, mixed>>  $roster
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function split(array $roster, ?CapitationBillingClosing $previous): array
    {
        $previousStart = $this->period->subMonthNoOverflow()->startOfMonth();
        $cutoff = $previousStart->endOfMonth()->startOfDay();
        $alreadyBilled = $previous === null ? [] : array_flip($previous->items()
            ->where('section', CapitationBillingClosingItem::SECTION_MONTH)
            ->get(['subject_type', 'subject_id'])
            ->map(fn (CapitationBillingClosingItem $item): string => $item->subject_type.':'.$item->subject_id)
            ->all());

        $month = [];
        $prorate = [];

        foreach ($roster as $row) {
            /** @var CarbonImmutable|null $enrolled */
            $enrolled = $row['enrolled_on'];

            if ($enrolled !== null && $enrolled->greaterThan($this->period)) {
                $this->skip('Dados de alta este mes: se prorratean en la pre-facturación del mes siguiente');

                continue;
            }

            if ($enrolled === null) {
                $row['observation'] = 'Sin fecha de alta registrada: se factura el mes completo.';
            }

            $month[] = $row;

            $key = $row['subject_type'].':'.$row['subject_id'];

            if ($enrolled !== null && $enrolled->greaterThan($previousStart) && ! isset($alreadyBilled[$key])) {
                ['days' => $days, 'amount' => $amount] = self::prorate((float) $row['monthly_rate'], $enrolled, $cutoff);

                $prorate[] = [
                    ...$row,
                    'section' => CapitationBillingClosingItem::SECTION_PRORATE,
                    'prorate_days' => $days,
                    'amount' => $amount,
                    'observation' => 'Alta '.$enrolled->format('d/m/Y').' · corte '.$cutoff->format('d/m/Y').' · '.$days.' '.($days === 1 ? 'día' : 'días'),
                ];
            }
        }

        return [$month, $prorate];
    }

    /**
     * @param  list<array<string, mixed>>  $monthRows
     * @return list<array<string, mixed>>
     */
    private function retirements(array $monthRows, ?CapitationBillingClosing $previous): array
    {
        $current = [];

        foreach ($monthRows as $row) {
            $current[$row['subject_type'].':'.$row['subject_id']] = true;
        }

        if ($previous !== null) {
            $rows = [];

            foreach ($previous->items()->where('section', CapitationBillingClosingItem::SECTION_MONTH)->orderBy('id')->get() as $item) {
                if (isset($current[$item->subject_type.':'.$item->subject_id])) {
                    continue;
                }

                $retiredOn = $this->retiredOn($item->subject_type, (int) $item->subject_id);

                $rows[] = [
                    ...self::itemToRow($item),
                    'section' => CapitationBillingClosingItem::SECTION_RETIREMENT,
                    'amount' => 0.0,
                    'prorate_days' => null,
                    'retired_on' => $retiredOn,
                    'observation' => $retiredOn === null ? 'Estaba en el cierre de '.mb_strtolower($previous->period->locale('es')->translatedFormat('F Y')).' y ya no aplica (sin fecha de baja registrada).' : 'Baja registrada el '.$retiredOn->format('d/m/Y').'.',
                ];
            }

            return $rows;
        }

        return $this->retirementsFromLedger($current);
    }

    /**
     * Sin cierre anterior: las bajas del mes anterior y de este mes registradas en la bitácora,
     * de afiliados de las afiliaciones del proveedor que hoy no están en el mes.
     *
     * @param  array<string, bool>  $current
     * @return list<array<string, mixed>>
     */
    private function retirementsFromLedger(array $current): array
    {
        $from = $this->period->subMonthNoOverflow()->startOfMonth();
        $to = $this->period->endOfMonth();

        $movements = AffiliateStatusMovement::query()
            ->whereIn('event', [AffiliateStatusMovement::EVENT_BAJA, AffiliateStatusMovement::EVENT_ELIMINADO])
            ->whereIn('subject_type', [CapitationSubject::AFFILIATE, CapitationSubject::AFFILIATE_CORPORATE, CapitationSubject::COMPANY_ASSOCIATE])
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get();

        $rows = [];

        foreach ($movements as $movement) {
            $key = $movement->subject_type.':'.$movement->subject_id;

            if (isset($current[$key]) || isset($rows[$key])) {
                continue;
            }

            $row = $this->retiredSubjectRow($movement->subject_type, (int) $movement->subject_id);

            if ($row === null) {
                continue;
            }

            $rows[$key] = [
                ...$row,
                'retired_on' => $movement->occurred_at->startOfDay(),
                'observation' => 'Baja registrada el '.$movement->occurred_at->format('d/m/Y').' ('.Str::lower((string) $movement->to_status ?: 'eliminado').').',
            ];
        }

        return array_values($rows);
    }

    /**
     * Datos de un afiliado dado de baja que pertenece a una afiliación del proveedor.
     *
     * @return array<string, mixed>|null
     */
    private function retiredSubjectRow(string $type, int $id): ?array
    {
        $base = [
            'section' => CapitationBillingClosingItem::SECTION_RETIREMENT,
            'subject_type' => $type,
            'subject_id' => $id,
            'capitation_plan' => null,
            'monthly_rate' => 0.0,
            'prorate_days' => null,
            'amount' => 0.0,
            'enrolled_on' => null,
        ];

        if ($type === CapitationSubject::AFFILIATE) {
            $affiliate = Affiliate::withTrashed()->with(['affiliation' => fn ($query) => $query->withTrashed(), 'plan:id,description'])->find($id);
            $affiliation = $affiliate?->affiliation;

            if (! $affiliation instanceof Affiliation || ! $this->linksSupplier($affiliation->getAttribute('service_providers'))) {
                return null;
            }

            return [...$base, ...$this->person('TDEC - INDIVIDUAL', AffiliationDocumentPrefix::forPayer($affiliation->getAttribute('document_type_payer'), $affiliation->nro_identificacion_payer, $affiliation->full_name_payer)->value, $affiliation->nro_identificacion_payer, (string) $affiliate->full_name, $affiliate->nro_identificacion, $affiliate->sex, $affiliate->birth_date, $affiliate->relationship), 'tdec_plan' => $affiliate->plan?->description];
        }

        if ($type === CapitationSubject::AFFILIATE_CORPORATE) {
            $affiliate = AffiliateCorporate::withTrashed()->with(['affiliationCorporate' => fn ($query) => $query->withTrashed(), 'plan:id,description'])->find($id);
            $affiliation = $affiliate?->affiliationCorporate;

            if (! $affiliation instanceof AffiliationCorporate || ! $this->linksSupplier($affiliation->getAttribute('service_providers'))) {
                return null;
            }

            return [...$base, ...$this->person(self::clean($affiliation->name_corporate) ?? 'CORPORATIVA', AffiliationDocumentPrefix::forCompany($affiliation->getAttribute('document_type_rif'))->value, $affiliation->rif, trim($affiliate->first_name.' '.$affiliate->last_name), $affiliate->nro_identificacion, $affiliate->sex, $affiliate->birth_date, $affiliate->relationship), 'tdec_plan' => $affiliate->plan?->description];
        }

        $associate = CompanyAssociate::query()->with('company')->find($id);
        $company = $associate?->company;

        if (! $company instanceof Company || ! $this->linksSupplier($company->getAttribute('service_providers'))) {
            return null;
        }

        return [...$base, ...$this->person(self::clean($company->name) ?? 'NUEVOS NEGOCIOS', AffiliationDocumentPrefix::forCompany(null)->value, $company->rif, (string) $associate->full_name, $associate->identity_card, $associate->sex, $associate->birth_date, 'TITULAR'), 'tdec_plan' => null];
    }

    private function retiredOn(string $type, int $id): ?CarbonImmutable
    {
        $movement = AffiliateStatusMovement::query()
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->whereIn('event', [AffiliateStatusMovement::EVENT_BAJA, AffiliateStatusMovement::EVENT_ELIMINADO])
            ->orderByDesc('occurred_at')
            ->first();

        return $movement?->occurred_at->startOfDay();
    }

    /**
     * La parte del capitado que corresponde a este proveedor (una afiliación puede llevar dos).
     *
     * @return array{plan: string, cost: float, overridden: bool}|null
     */
    private function line(CapitationCostResult $result): ?array
    {
        if (! $result->applies()) {
            $this->skip($result->reason ?? 'Sin capitado');

            return null;
        }

        $lines = array_values(array_filter($result->lines, fn (array $line): bool => (int) ($line['supplier_id'] ?? 0) === (int) $this->supplier->getKey()));

        if ($lines === []) {
            $this->skip('El capitado de la afiliación corresponde a otro proveedor');

            return null;
        }

        /** El ajuste negociado reemplaza la tarifa solo si el afiliado cotiza a un único proveedor. */
        $overridden = $result->isOverridden() && count($result->lines) === 1;
        $cost = $overridden ? $result->amount : (float) array_sum(array_column($lines, 'cost'));

        return ['plan' => (string) $lines[0]['plan'], 'cost' => round($cost, 2), 'overridden' => $overridden];
    }

    /**
     * @param  array{plan: string, cost: float, overridden: bool}  $line
     * @return array<string, mixed>
     */
    private function row(string $subjectType, int $subjectId, string $group, ?string $holderDocumentType, mixed $holderDocument, string $name, mixed $document, mixed $sex, mixed $birthDate, mixed $relationship, array $line, ?string $tdecPlan, ?CarbonImmutable $enrolledOn): array
    {
        return [
            'section' => CapitationBillingClosingItem::SECTION_MONTH,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            ...$this->person($group, $holderDocumentType, $holderDocument, $name, $document, $sex, $birthDate, $relationship),
            'capitation_plan' => $line['plan'],
            'tdec_plan' => self::clean($tdecPlan),
            'enrolled_on' => $enrolledOn,
            'monthly_rate' => $line['cost'],
            'prorate_days' => null,
            'amount' => $line['cost'],
            'retired_on' => null,
            'observation' => $line['overridden'] ? 'Tarifa negociada para este afiliado.' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function person(string $group, ?string $holderDocumentType, mixed $holderDocument, string $name, mixed $document, mixed $sex, mixed $birthDate, mixed $relationship): array
    {
        $document = self::clean($document);

        return [
            'holder_group' => Str::limit(Str::squish($group), 250, ''),
            'holder_document_type' => $holderDocumentType,
            'holder_document' => self::clean($holderDocument),
            'affiliate_name' => Str::limit(self::personName($name), 250, ''),
            'affiliate_document_type' => AffiliationDocumentPrefix::inferPerson($document)->value,
            'affiliate_document' => $document,
            'sex' => self::sex($sex),
            'birth_date' => self::date($birthDate),
            'relationship' => ($value = self::clean($relationship)) !== null ? Str::upper($value) : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function notes(string $alias, ?CapitationBillingClosing $previous): array
    {
        $notes = [];

        if ($alias === '') {
            $notes[] = 'El proveedor no tiene alias de Integracorp configurado: no se puede saber qué afiliaciones lo llevan.';
        }

        if ($previous === null) {
            $notes[] = 'El mes anterior no está cerrado: los retiros salen de la bitácora de bajas (registrada desde el 05/11/2026) y el prorrateo incluye a todos los dados de alta ese mes.';
        }

        foreach ($this->skipped as $reason => $count) {
            if ($reason === 'Afiliado no activo' || $reason === 'Sin afiliación activa') {
                continue;
            }

            $notes[] = number_format($count, 0, ',', '.').' '.($count === 1 ? 'afiliado no entra' : 'afiliados no entran').': '.$reason.'.';
        }

        return $notes;
    }

    private function linksSupplier(mixed $serviceProviders): bool
    {
        return in_array($this->alias, AffiliateCapitationCost::aliases($serviceProviders), true);
    }

    private function skip(string $reason): void
    {
        $this->skipped[$reason] = ($this->skipped[$reason] ?? 0) + 1;
    }

    private function planName(int $planId): ?string
    {
        if ($planId <= 0) {
            return null;
        }

        if (! array_key_exists($planId, $this->planNames)) {
            $this->planNames[$planId] = (string) (Plan::query()->whereKey($planId)->value('description') ?? '');
        }

        return self::clean($this->planNames[$planId]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function itemToRow(CapitationBillingClosingItem $item): array
    {
        return [
            'section' => (string) $item->section,
            'subject_type' => (string) $item->subject_type,
            'subject_id' => (int) $item->subject_id,
            'holder_group' => (string) $item->holder_group,
            'holder_document_type' => $item->holder_document_type,
            'holder_document' => $item->holder_document,
            'affiliate_name' => (string) $item->affiliate_name,
            'affiliate_document_type' => $item->affiliate_document_type,
            'affiliate_document' => $item->affiliate_document,
            'sex' => $item->sex,
            'birth_date' => $item->birth_date,
            'relationship' => $item->relationship,
            'capitation_plan' => $item->capitation_plan,
            'tdec_plan' => $item->tdec_plan,
            'enrolled_on' => $item->enrolled_on,
            'monthly_rate' => (float) $item->monthly_rate,
            'prorate_days' => $item->prorate_days,
            'amount' => (float) $item->amount,
            'retired_on' => $item->retired_on,
            'observation' => $item->observation,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function sorted(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => [Str::upper($a['holder_group']), Str::upper($a['affiliate_name']), $a['subject_id']]
            <=> [Str::upper($b['holder_group']), Str::upper($b['affiliate_name']), $b['subject_id']]);

        return $rows;
    }

    public static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        $value = trim((string) ($value ?? ''));

        if ($value === '' || ! preg_match('/\d/', $value)) {
            return null;
        }

        foreach (['!d/m/Y', '!j/n/Y', '!Y-m-d', '!d-m-Y', '!Y-m-d H:i:s', '!d/m/Y H:i:s'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value);
            } catch (Throwable) {
                continue;
            }

            /** Sin desbordes: «31/02/2026» no es el 3 de marzo, es una fecha inválida. */
            if ($date !== false && $date->format(ltrim($format, '!')) === $value && $date->year > 1900 && $date->year < 2100) {
                return $date->startOfDay();
            }
        }

        /** Último recurso solo para fechas ISO con hora o zona (las de los timestamps). */
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);

            return $date->year > 1900 && $date->year < 2100 ? $date->startOfDay() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function latest(?CarbonImmutable $a, ?CarbonImmutable $b): ?CarbonImmutable
    {
        return match (true) {
            $a === null => $b,
            $b === null => $a,
            default => $a->greaterThan($b) ? $a : $b,
        };
    }

    /**
     * Nombre sin los rellenos que trae la carga de población («...», «N/A»).
     */
    public static function personName(string $name): string
    {
        $name = Str::squish((string) preg_replace('/(?<=^|\s)(\.{2,}|N\/A)(?=\s|$)/i', ' ', $name));

        return $name !== '' ? $name : 'SIN NOMBRE';
    }

    private static function sex(mixed $value): ?string
    {
        $value = Str::upper(Str::ascii(trim((string) ($value instanceof BackedEnum ? $value->value : ($value ?? '')))));

        return match (true) {
            $value === '' => null,
            str_starts_with($value, 'F') => 'FEMENINO',
            str_starts_with($value, 'M') => 'MASCULINO',
            default => $value,
        };
    }

    private static function clean(mixed $value): ?string
    {
        $value = Str::squish((string) ($value ?? ''));

        return $value === '' || $value === 'N/A' || $value === '...' ? null : $value;
    }
}
