<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliationCorporate;
use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * El plan contratado de una afiliación corporativa vive en
 * `afilliation_corporate_plans`: puede haber varias filas (rangos de edad),
 * pero todas deben compartir el mismo `plan_id`.
 */
final class CorporateAffiliationContractedPlan
{
    public static function planId(AffiliationCorporate $record): ?int
    {
        $planId = self::planRows($record)
            ->pluck('plan_id')
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return $planId !== null ? (int) $planId : null;
    }

    public static function plan(AffiliationCorporate $record): ?Plan
    {
        $planId = self::planId($record);

        if ($planId === null) {
            return null;
        }

        foreach (self::planRows($record) as $row) {
            if ((int) $row->plan_id !== $planId) {
                continue;
            }

            if ($row->relationLoaded('plan') && $row->plan instanceof Plan) {
                self::ensureBenefitPlansLoaded($row->plan);

                return $row->plan;
            }
        }

        if (! $record->exists) {
            return null;
        }

        return Plan::query()->with('benefitPlans')->find($planId);
    }

    /**
     * @return array{plan: string, plan_id: int|null}
     */
    public static function certificateFields(AffiliationCorporate $record): array
    {
        $plan = self::plan($record);

        return [
            'plan' => trim((string) ($plan?->description ?? '')),
            'plan_id' => $plan !== null ? (int) $plan->id : self::planId($record),
        ];
    }

    /**
     * @return list<string>
     */
    public static function benefitDescriptions(AffiliationCorporate $record): array
    {
        $plan = self::plan($record);

        if ($plan === null) {
            return [];
        }

        self::ensureBenefitPlansLoaded($plan);

        if (! $plan->relationLoaded('benefitPlans')) {
            return [];
        }

        return $plan->benefitPlans
            ->pluck('description')
            ->filter(fn (mixed $description): bool => filled($description))
            ->map(fn (mixed $description): string => (string) $description)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, \App\Models\AfilliationCorporatePlan>
     */
    private static function planRows(AffiliationCorporate $record): Collection
    {
        if (! $record->relationLoaded('affiliationCorporatePlans')) {
            if ($record->exists) {
                $record->loadMissing(['affiliationCorporatePlans.plan']);
            } else {
                $record->setRelation('affiliationCorporatePlans', collect());
            }
        }

        return $record->affiliationCorporatePlans;
    }

    private static function ensureBenefitPlansLoaded(Plan $plan): void
    {
        if ($plan->relationLoaded('benefitPlans')) {
            return;
        }

        if ($plan->exists) {
            $plan->loadMissing('benefitPlans');
        }
    }
}
