<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * Secciones de beneficios del certificado corporativo, una por plan presente en la población.
 *
 * Una corporativa puede tener afiliados en planes distintos (`affiliate_corporates.plan_id`).
 * Listar solo los beneficios del plan mayoritario dejaba al resto con un certificado que no
 * describe su cobertura, así que se emite un bloque por plan y la tabla de afiliados indica
 * a cuál pertenece cada persona.
 */
final class CorporateCertificateBenefitSections
{
    public const PREEXISTENCE_NOTE = 'LUEGO DEL ANÁLISIS TÉCNICO Y MÉDICO DE LA SOLICITUD, QUEDA EXCLUIDO DEL BENEFICIO DE EMERGENCIAS MÉDICAS POR PATOLOGÍAS LISTADAS, TODA OCURRENCIA RELACIONADA Y/O A CONSECUENCIA DE LAS PREEXISTENCIAS DECLARADAS O NO. ANTE ALGÚN EVENTO INESPERADO ASOCIADO A LAS PREEXISTENCIAS DECLARADAS Y EN CONOCIMIENTO O NO, SERÁ ESTABILIZADO EN SU DOMICILIO EN EL MOMENTO QUE SEA REQUERIDO.';

    /**
     * Beneficios cuyo importe se imprime en lugar del check, cuando hay cobertura contratada.
     */
    private const COVERAGE_BENEFITS = [
        'EMERGENCIAS MÉDICAS POR PATOLOGIAS LISTADAS',
        'ASISTENCIA MÉDICA POR ACCIDENTES',
    ];

    /**
     * @return list<int> ids de plan presentes en la población, en orden de aparición
     */
    public static function planIdsForPopulation(AffiliationCorporate $record): array
    {
        $fromAffiliates = self::affiliates($record)
            ->pluck('plan_id')
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($fromAffiliates !== []) {
            return $fromAffiliates;
        }

        $contracted = CorporateAffiliationContractedPlan::planId($record);

        return $contracted !== null ? [$contracted] : [];
    }

    /**
     * Etiqueta del plan de un afiliado; cae al plan contratado cuando la fila no lo indica.
     *
     * @param  array<int, string>  $planNames
     */
    public static function planLabelForAffiliate(?int $planId, array $planNames, string $fallback): string
    {
        if ($planId !== null && isset($planNames[$planId])) {
            return $planNames[$planId];
        }

        return $fallback;
    }

    /**
     * @return array<int, string> id de plan => descripción
     */
    public static function planNames(AffiliationCorporate $record): array
    {
        $ids = self::planIdsForPopulation($record);

        if ($ids === []) {
            return [];
        }

        return Plan::query()
            ->whereIn('id', $ids)
            ->pluck('description', 'id')
            ->map(fn (mixed $description): string => trim((string) $description))
            ->all();
    }

    /**
     * Una sección por plan, con sus beneficios y su nota legal si el plan la exige.
     *
     * @return list<array{plan_id: int|null, plan_label: string, rows: list<array{text: string, show_cobertura: bool}>, note: string|null}>
     */
    public static function forAffiliation(AffiliationCorporate $record, bool $hasCoverageAmount): array
    {
        $planIds = self::planIdsForPopulation($record);

        if ($planIds === []) {
            return [];
        }

        $plans = Plan::query()
            ->with('benefitPlans')
            ->whereIn('id', $planIds)
            ->get()
            ->keyBy(fn (Plan $plan): int => (int) $plan->id);

        $sections = [];

        foreach ($planIds as $planId) {
            $plan = $plans->get($planId);

            if ($plan === null) {
                continue;
            }

            $rows = self::rowsForPlan($plan, $hasCoverageAmount);

            if ($rows === []) {
                continue;
            }

            $sections[] = [
                'plan_id' => $planId,
                'plan_label' => trim((string) $plan->description),
                'rows' => $rows,
                'note' => $plan->requires_preexistence_note ? self::PREEXISTENCE_NOTE : null,
            ];
        }

        return $sections;
    }

    /**
     * @return list<array{text: string, show_cobertura: bool}>
     */
    private static function rowsForPlan(Plan $plan, bool $hasCoverageAmount): array
    {
        if (! $plan->relationLoaded('benefitPlans')) {
            $plan->loadMissing('benefitPlans');
        }

        return $plan->benefitPlans
            ->pluck('description')
            ->filter(fn (mixed $description): bool => filled($description))
            ->map(fn (mixed $description): array => [
                'text' => (string) $description,
                'show_cobertura' => $hasCoverageAmount
                    && in_array(trim((string) $description), self::COVERAGE_BENEFITS, true),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, AffiliateCorporate>
     */
    private static function affiliates(AffiliationCorporate $record): Collection
    {
        if (! $record->relationLoaded('corporateAffiliates')) {
            if (! $record->exists) {
                return collect();
            }

            $record->loadMissing('corporateAffiliates');
        }

        return $record->corporateAffiliates;
    }
}
