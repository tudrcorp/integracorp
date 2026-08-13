<?php

declare(strict_types=1);

namespace App\Support\CreditReconciliations;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Models\WhiteCompany;
use App\Support\Affiliation\AffiliationDocumentAffiliatesCount;

final class CreditReconciliationAffiliationSnapshot
{
    public const KIND_INDIVIDUAL = 'individual';

    public const KIND_CORPORATE = 'corporate';

    public static function whiteCompanyForAgencyCode(?string $agencyCode): ?WhiteCompany
    {
        if (blank($agencyCode)) {
            return null;
        }

        $whiteCompanyId = User::query()
            ->where('code_agency', $agencyCode)
            ->whereNotNull('white_company_id')
            ->orderBy('id')
            ->value('white_company_id');

        if ($whiteCompanyId === null) {
            return null;
        }

        return WhiteCompany::query()->find($whiteCompanyId);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromIndividual(Affiliation $affiliation): array
    {
        $plan = $affiliation->plan;
        $planType = filled($plan?->description)
            ? (string) $plan->description
            : (filled($plan?->type) ? (string) $plan->type : null);

        return [
            'affiliation_kind' => self::KIND_INDIVIDUAL,
            'affiliation_id' => $affiliation->id,
            'affiliation_corporate_id' => null,
            'affiliation_code' => (string) $affiliation->code,
            'affiliation_information' => self::individualInformation($affiliation),
            'affiliates_count' => $affiliation->relationLoaded('affiliates')
                ? $affiliation->affiliates->count()
                : AffiliationDocumentAffiliatesCount::forIndividual($affiliation),
            'annual_amount' => (float) ($affiliation->fee_anual ?? 0),
            'payment_frequency' => $affiliation->payment_frequency,
            'plan_id' => $affiliation->plan_id,
            'plan_type' => $planType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromCorporate(AffiliationCorporate $affiliation): array
    {
        $planType = filled($affiliation->affiliation_type)
            ? (string) $affiliation->affiliation_type
            : (filled($affiliation->type) ? (string) $affiliation->type : null);

        return [
            'affiliation_kind' => self::KIND_CORPORATE,
            'affiliation_id' => null,
            'affiliation_corporate_id' => $affiliation->id,
            'affiliation_code' => (string) $affiliation->code,
            'affiliation_information' => self::corporateInformation($affiliation),
            'affiliates_count' => $affiliation->relationLoaded('corporateAffiliates')
                ? $affiliation->corporateAffiliates->count()
                : AffiliationDocumentAffiliatesCount::forCorporate($affiliation),
            'annual_amount' => (float) ($affiliation->fee_anual ?? 0),
            'payment_frequency' => $affiliation->payment_frequency,
            'plan_id' => null,
            'plan_type' => $planType,
        ];
    }

    private static function individualInformation(Affiliation $affiliation): string
    {
        $titular = trim((string) $affiliation->full_name_ti);
        $document = trim((string) $affiliation->nro_identificacion_ti);
        $payer = trim((string) $affiliation->full_name_payer);

        return implode("\n", array_values(array_filter([
            'Código: '.(string) $affiliation->code,
            $titular !== '' ? 'Titular: '.$titular.($document !== '' ? ' ('.$document.')' : '') : null,
            $payer !== '' ? 'Pagador: '.$payer : null,
            filled($affiliation->status) ? 'Estatus: '.$affiliation->status : null,
            filled($affiliation->code_agency) ? 'Agencia: '.$affiliation->code_agency : null,
        ])));
    }

    private static function corporateInformation(AffiliationCorporate $affiliation): string
    {
        $name = trim((string) $affiliation->name_corporate);
        $rif = trim((string) $affiliation->rif);
        $contact = trim((string) $affiliation->full_name_contact);

        return implode("\n", array_values(array_filter([
            'Código: '.(string) $affiliation->code,
            $name !== '' ? 'Empresa: '.$name.($rif !== '' ? ' ('.$rif.')' : '') : null,
            $contact !== '' ? 'Contacto: '.$contact : null,
            filled($affiliation->status) ? 'Estatus: '.$affiliation->status : null,
            filled($affiliation->code_agency) ? 'Agencia: '.$affiliation->code_agency : null,
        ])));
    }
}
