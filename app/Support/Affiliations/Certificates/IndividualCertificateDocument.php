<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AffiliationCertificateIssue;
use App\Models\PaidMembership;
use App\Support\Collections\CollectionDueDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

/**
 * Datos del certificado y los carnets de una afiliación individual, en la forma
 * que espera la plantilla `documents.affiliation-certificate.document`.
 *
 * Todo sale de la afiliación: no se pide nada a mano al analista salvo qué
 * afiliados llevan carnet.
 */
final class IndividualCertificateDocument
{
    /** Afiliados que se imprimen: los vigentes, como en las renovaciones. */
    public const PRINTABLE_STATUSES = ['ACTIVO', 'PRE-APROBADA'];

    /**
     * Relaciones que necesita el documento; cargarlas juntas evita consultas por fila.
     *
     * @return list<string>
     */
    public static function eagerLoads(): array
    {
        return ['plan.benefitPlans', 'coverage:id,price', 'agent:id,name', 'agency:id,code,name_corporative', 'affiliates'];
    }

    /**
     * Afiliados que pueden llevar carnet, con el titular primero.
     *
     * @return SupportCollection<int, Affiliate>
     */
    public static function printableAffiliates(Affiliation $affiliation): SupportCollection
    {
        $affiliation->loadMissing('affiliates');

        return $affiliation->affiliates
            ->filter(fn (Affiliate $affiliate): bool => in_array(Str::upper(trim((string) $affiliate->status)), self::PRINTABLE_STATUSES, true))
            ->sortBy(fn (Affiliate $affiliate): string => (Str::upper(trim((string) $affiliate->relationship)) === 'TITULAR' ? '0' : '1').str_pad((string) $affiliate->id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * @param  list<int>  $carnetAffiliateIds
     * @return array<string, mixed>
     */
    public static function build(Affiliation $affiliation, AffiliationCertificateIssue $issue, array $carnetAffiliateIds, ?CarbonImmutable $today = null): array
    {
        $affiliation->loadMissing(self::eagerLoads());
        $today ??= CarbonImmutable::today();

        $planLabel = trim((string) ($affiliation->plan?->description ?? ''));
        $period = self::paymentPeriod($affiliation, $today);
        $coveragePrice = is_numeric($affiliation->coverage?->price) ? (float) $affiliation->coverage->price : null;
        $benefits = CertificateBenefits::for($affiliation->plan, $affiliation->coverage_id ? (int) $affiliation->coverage_id : null, $coveragePrice);

        $people = self::printableAffiliates($affiliation)
            ->values()
            ->map(fn (Affiliate $affiliate, int $index): array => [
                'id' => (int) $affiliate->id,
                'num' => $index + 1,
                'nombre' => CertificateFormat::name($affiliate->full_name),
                'docFmt' => CertificateFormat::document($affiliate->nro_identificacion),
                'nac' => CollectionDueDate::parse($affiliate->birth_date)?->format('d/m/Y') ?? '—',
                'parentesco' => CertificateFormat::relationship($affiliate->relationship),
            ])
            ->all();

        if ($people === []) {
            $people[] = [
                'id' => 0,
                'num' => 1,
                'nombre' => CertificateFormat::name($affiliation->full_name_ti),
                'docFmt' => CertificateFormat::document($affiliation->nro_identificacion_ti),
                'nac' => CollectionDueDate::parse($affiliation->birth_date_ti)?->format('d/m/Y') ?? '—',
                'parentesco' => 'Titular',
            ];
        }

        $selected = array_flip(array_map('intval', $carnetAffiliateIds));
        $carnets = array_values(array_filter($people, static fn (array $person): bool => isset($selected[$person['id']])));

        $frequency = Str::lower(trim((string) ($affiliation->payment_frequency ?: 'anual')));
        $periodAmount = is_numeric($affiliation->total_amount) && (float) $affiliation->total_amount > 0 ? (float) $affiliation->total_amount : null;
        $annualFee = is_numeric($affiliation->fee_anual) && (float) $affiliation->fee_anual > 0 ? (float) $affiliation->fee_anual : null;

        return CertificateDocumentData::assemble([
            'tipo' => AffiliationCertificateIssue::TYPE_INDIVIDUAL,
            'codigo' => (string) $affiliation->code,
            'planLabel' => $planLabel !== '' ? $planLabel : 'Plan',
            'benefits' => $benefits,
            'preexNote' => (bool) ($affiliation->plan?->requires_preexistence_note),
            'heroSub' => 'Pago '.$frequency.($periodAmount !== null ? ' · Tarifa del período '.CertificateBenefits::money($periodAmount) : ' · Tarifa según contrato'),
            'period' => $period,
            'contratante' => CertificateFormat::name($affiliation->full_name_payer ?: $affiliation->full_name_ti),
            'contratanteId' => CertificateFormat::document($affiliation->nro_identificacion_payer ?: $affiliation->nro_identificacion_ti),
            'agente' => CertificateFormat::name($affiliation->agent?->name ?: $affiliation->agency?->name_corporative ?: 'Tu Doctor en Casa'),
            'tarifaAnual' => $annualFee !== null ? CertificateBenefits::money($annualFee) : 'Según contrato',
            'fechaAfiliacion' => CollectionDueDate::parse($affiliation->activated_at)?->format('d/m/Y')
                ?? $affiliation->created_at?->format('d/m/Y') ?? '—',
            'people' => $people,
            'carnets' => $carnets,
            'grupoTitulo' => 'Afiliados del grupo familiar',
            'issue' => $issue,
        ]);
    }

    public static function paymentPeriod(Affiliation $affiliation, ?CarbonImmutable $today = null): ?CertificatePaymentPeriod
    {
        $effective = CollectionDueDate::parse($affiliation->effective_date) ?? CollectionDueDate::parse($affiliation->activated_at);

        if ($effective === null) {
            return null;
        }

        $initialPayments = PaidMembership::query()
            ->where('affiliation_id', $affiliation->id)
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
}
