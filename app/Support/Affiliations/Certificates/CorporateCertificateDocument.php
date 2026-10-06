<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCertificateIssue;
use App\Models\AffiliationCorporate;
use App\Models\Coverage;
use App\Models\PaidMembershipCorporate;
use App\Models\Plan;
use App\Support\AffiliationCorporateRifLabel;
use App\Support\AffiliationCorporates\CorporateAffiliateRelationship;
use App\Support\AffiliationCorporates\CorporateAffiliationContractedPlan;
use App\Support\Collections\CollectionDueDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

/**
 * Datos del certificado y los carnets de una afiliación corporativa.
 *
 * A diferencia de la individual, `affiliation_corporates` no tiene plan ni
 * cobertura propios: van por afiliado. El encabezado toma el plan mayoritario; si
 * el colectivo tiene varios planes, el certificado se titula «CORPORATIVO» y lista
 * los beneficios de cada plan, y cada carnet lleva el plan de su afiliado.
 */
final class CorporateCertificateDocument
{
    public const PRINTABLE_STATUSES = IndividualCertificateDocument::PRINTABLE_STATUSES;

    /**
     * @return SupportCollection<int, AffiliateCorporate>
     */
    public static function printableAffiliates(AffiliationCorporate $affiliation): SupportCollection
    {
        return AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliation->id)
            ->whereIn('status', self::PRINTABLE_STATUSES)
            ->orderBy('id')
            ->get(['id', 'affiliation_corporate_id', 'first_name', 'last_name', 'nro_identificacion', 'birth_date', 'relationship', 'plan_id', 'coverage_id', 'status']);
    }

    /**
     * Nombre y apellido. Hay cargas donde `first_name` ya trae los apellidos
     * («AZUAJE RODRIGUEZ DIEGO ALESSANDRO» + «AZUAJE RODRIGUEZ»): no se repiten.
     */
    public static function fullName(AffiliateCorporate $affiliate): string
    {
        $first = trim(preg_replace('/\s+/', ' ', (string) $affiliate->first_name) ?? '');
        $last = trim(preg_replace('/\s+/', ' ', (string) $affiliate->last_name) ?? '');

        if ($last !== '' && $first !== '' && str_contains(mb_strtoupper($first), mb_strtoupper($last))) {
            return CertificateFormat::name($first);
        }

        return CertificateFormat::name(trim($first.' '.$last));
    }

    /**
     * @param  list<int>  $carnetAffiliateIds
     * @return array<string, mixed>
     */
    public static function build(AffiliationCorporate $affiliation, AffiliationCertificateIssue $issue, array $carnetAffiliateIds, ?CarbonImmutable $today = null): array
    {
        $affiliation->loadMissing(['agent:id,name', 'agency:id,code,name_corporative', 'affiliationCorporatePlans.plan']);
        $today ??= CarbonImmutable::today();

        $affiliates = self::printableAffiliates($affiliation);
        $mainPlan = CorporateAffiliationContractedPlan::plan($affiliation);

        $planIds = $affiliates->pluck('plan_id')->filter()->map(static fn (mixed $id): int => (int) $id)->unique()->values();

        if ($planIds->isEmpty() && $mainPlan !== null) {
            $planIds = collect([(int) $mainPlan->id]);
        }

        $plans = Plan::query()->with('benefitPlans')->whereIn('id', $planIds->all())->get()->keyBy('id');
        $coverages = Coverage::query()->whereIn('id', $affiliates->pluck('coverage_id')->filter()->unique()->all())->pluck('price', 'id');
        $multiplePlans = $plans->count() > 1;

        // Un resumen de beneficios por combinación plan + cobertura: se calcula una vez, no por afiliado.
        $benefitsByCombo = [];
        $benefitsFor = function (?int $planId, ?int $coverageId) use (&$benefitsByCombo, $plans, $coverages): array {
            $key = ($planId ?? 0).':'.($coverageId ?? 0);

            return $benefitsByCombo[$key] ??= CertificateBenefits::for(
                $planId !== null ? $plans->get($planId) : null,
                $coverageId,
                $coverageId !== null && is_numeric($coverages->get($coverageId)) ? (float) $coverages->get($coverageId) : null,
            );
        };

        $mainPlanId = $mainPlan !== null ? (int) $mainPlan->id : $planIds->first();
        $mainCoverageId = self::dominantCoverage($affiliates, $mainPlanId);
        $mainBenefits = $benefitsFor($mainPlanId, $mainCoverageId);

        $people = $affiliates->values()->map(function (AffiliateCorporate $affiliate, int $index) use ($plans, $benefitsFor, $multiplePlans): array {
            $planId = $affiliate->plan_id ? (int) $affiliate->plan_id : null;
            $planLabel = (string) ($plans->get($planId ?? 0)?->description ?? '');

            return [
                'id' => (int) $affiliate->id,
                'num' => $index + 1,
                'nombre' => self::fullName($affiliate),
                'docFmt' => CertificateFormat::document($affiliate->nro_identificacion),
                'nac' => CollectionDueDate::parse($affiliate->birth_date)?->format('d/m/Y') ?? '—',
                // Con varios planes la columna es «Plan»: sin plan asignado se deja «—», no el parentesco.
                'parentesco' => $multiplePlans
                    ? ($planLabel !== '' ? CertificateDocumentData::shortPlanName($planLabel) : '—')
                    : Str::ucfirst(mb_strtolower(CorporateAffiliateRelationship::forCertificate($affiliate->relationship))),
                'plan' => $planLabel !== '' ? CertificateDocumentData::shortPlanName($planLabel) : null,
                'cobertura' => $benefitsFor($planId, $affiliate->coverage_id ? (int) $affiliate->coverage_id : null)['carnet'],
            ];
        })->all();

        $selected = array_flip(array_map('intval', $carnetAffiliateIds));
        $carnets = array_values(array_filter($people, static fn (array $person): bool => isset($selected[$person['id']])));

        $benefits = $multiplePlans ? self::multiPlanBenefits($plans, $affiliates, $benefitsFor, $mainPlanId) : $mainBenefits;
        $planLabel = $multiplePlans ? 'CORPORATIVO' : (string) ($plans->get($mainPlanId ?? 0)?->description ?? $mainPlan?->description ?? 'CORPORATIVO');

        $frequency = Str::lower(trim((string) ($affiliation->payment_frequency ?: 'anual')));
        $periodAmount = self::number($affiliation->total_amount);
        $annualFee = self::number($affiliation->fee_anual);
        $rif = AffiliationCorporateRifLabel::withJPrefix($affiliation->rif);

        return CertificateDocumentData::assemble([
            'tipo' => AffiliationCertificateIssue::TYPE_CORPORATE,
            'codigo' => (string) $affiliation->code,
            'planLabel' => $planLabel,
            'benefits' => $benefits,
            'preexNote' => $plans->contains(fn (Plan $plan): bool => (bool) $plan->requires_preexistence_note),
            'heroSub' => 'Pago '.$frequency
                .($periodAmount !== null ? ' · Tarifa del período '.CertificateBenefits::money($periodAmount) : ' · Tarifa según contrato')
                .($multiplePlans ? ' · '.$plans->count().' planes contratados' : ''),
            'period' => self::paymentPeriod($affiliation, $today),
            'contratante' => trim((string) $affiliation->name_corporate) !== '' ? Str::upper(trim((string) $affiliation->name_corporate)) : '—',
            'contratanteId' => $rif !== '' ? 'RIF '.$rif : '—',
            'agente' => CertificateFormat::name($affiliation->agent?->name ?: $affiliation->agency?->name_corporative ?: 'Tu Doctor en Casa'),
            'tarifaAnual' => $annualFee !== null ? CertificateBenefits::money($annualFee) : 'Según contrato',
            'fechaAfiliacion' => CollectionDueDate::parse($affiliation->activated_at)?->format('d/m/Y')
                ?? $affiliation->created_at?->format('d/m/Y') ?? '—',
            'people' => $people,
            'carnets' => $carnets,
            'grupoTitulo' => 'Afiliados del colectivo',
            'relationPlanColumn' => $multiplePlans,
            'issue' => $issue,
        ]);
    }

    public static function paymentPeriod(AffiliationCorporate $affiliation, ?CarbonImmutable $today = null): ?CertificatePaymentPeriod
    {
        $effective = CollectionDueDate::parse($affiliation->effective_date) ?? CollectionDueDate::parse($affiliation->activated_at);

        if ($effective === null) {
            return null;
        }

        $initialPayments = PaidMembershipCorporate::query()
            ->where('affiliation_corporate_id', $affiliation->id)
            ->whereIn('status', ['APROBADO', 'PAGADO'])
            ->pluck('payment_date')
            ->map(static fn (mixed $date): string => (string) $date)
            ->all();

        return CertificatePaymentPeriod::resolve(
            $effective,
            $affiliation->payment_frequency,
            CertificatePaymentPeriod::paidCollectionDatesFor((string) $affiliation->code),
            $initialPayments,
            $today,
        );
    }

    /**
     * Varios planes: un bloque de beneficios por plan, cada uno con su título, y la
     * franja principal la del plan mayoritario.
     *
     * @param  SupportCollection<int, Plan>  $plans
     * @param  SupportCollection<int, AffiliateCorporate>  $affiliates
     * @param  callable(?int, ?int): array{rows: list<array{t: string, cob: string, limited: bool}>, hero: array{label: string, value: string, note: string}, carnet: string, emergency: bool}  $benefitsFor
     * @return array{rows: list<array<string, mixed>>, hero: array{label: string, value: string, note: string}, carnet: string, emergency: bool}
     */
    private static function multiPlanBenefits(SupportCollection $plans, SupportCollection $affiliates, callable $benefitsFor, ?int $mainPlanId): array
    {
        $rows = [];
        $emergency = false;
        $main = null;

        $ordered = $plans->sortBy(fn (Plan $plan): int => (int) $plan->id === $mainPlanId ? 0 : 1);

        foreach ($ordered as $plan) {
            $summary = $benefitsFor((int) $plan->id, self::dominantCoverage($affiliates, (int) $plan->id));
            $main ??= $summary;
            $emergency = $emergency || $summary['emergency'];

            $rows[] = ['section' => (string) $plan->description, 't' => '', 'cob' => '', 'limited' => false];
            array_push($rows, ...$summary['rows']);
        }

        return [
            'rows' => $rows,
            'hero' => ['label' => 'Planes contratados', 'value' => $plans->count().' planes', 'note' => 'Cada afiliado según su plan y cobertura'],
            'carnet' => $main['carnet'] ?? 'Según plan',
            'emergency' => $emergency,
        ];
    }

    /**
     * La cobertura más frecuente entre los afiliados de un plan.
     *
     * @param  SupportCollection<int, AffiliateCorporate>  $affiliates
     */
    private static function dominantCoverage(SupportCollection $affiliates, ?int $planId): ?int
    {
        $coverageId = $affiliates
            ->filter(fn (AffiliateCorporate $affiliate): bool => $planId === null || (int) $affiliate->plan_id === $planId)
            ->pluck('coverage_id')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return $coverageId !== null ? (int) $coverageId : null;
    }

    private static function number(mixed $value): ?float
    {
        $clean = is_string($value) ? str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', $value) ?? '') : $value;

        return is_numeric($clean) && (float) $clean > 0 ? (float) $clean : null;
    }
}
