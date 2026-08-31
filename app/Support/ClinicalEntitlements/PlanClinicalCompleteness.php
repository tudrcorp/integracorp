<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Models\BenefitPlan;
use App\Models\Plan;
use App\Models\PlanBenefitClinicalSetting;
use Illuminate\Support\Collection;

final class PlanClinicalCompleteness
{
    /**
     * Un plan está listo para telemedicina cuando cada beneficio asignado
     * tiene fila de uso clínico y esa fila es válida.
     */
    public static function isComplete(?Plan $plan): bool
    {
        if ($plan === null || $plan->id === null) {
            return false;
        }

        if (self::assignedBenefitIds($plan) === []) {
            return false;
        }

        return self::missingBenefitIds($plan) === [];
    }

    /**
     * @return list<int>
     */
    public static function missingBenefitIds(Plan $plan): array
    {
        $assigned = self::assignedBenefitIds($plan);
        if ($assigned === []) {
            return [];
        }

        $settings = $plan->relationLoaded('clinicalSettings')
            ? $plan->clinicalSettings->keyBy('benefit_id')
            : PlanBenefitClinicalSetting::query()
                ->where('plan_id', $plan->id)
                ->whereIn('benefit_id', $assigned)
                ->get()
                ->keyBy('benefit_id');

        $missing = [];

        foreach ($assigned as $benefitId) {
            $row = $settings->get($benefitId);
            if (! $row instanceof PlanBenefitClinicalSetting || ! $row->isComplete()) {
                $missing[] = $benefitId;
            }
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    public static function missingBenefitLabels(Plan $plan): array
    {
        $ids = self::missingBenefitIds($plan);
        if ($ids === []) {
            return [];
        }

        return BenefitPlan::query()
            ->where('plan_id', $plan->id)
            ->whereIn('benefit_id', $ids)
            ->orderBy('description')
            ->pluck('description')
            ->map(static fn (mixed $label): string => trim((string) $label))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function assignedBenefitIds(Plan $plan): array
    {
        if ($plan->relationLoaded('benefitPlans')) {
            return $plan->benefitPlans
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return BenefitPlan::query()
            ->where('plan_id', $plan->id)
            ->pluck('benefit_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, PlanBenefitClinicalSetting>
     */
    public static function settingsForPlan(Plan $plan): Collection
    {
        return PlanBenefitClinicalSetting::query()
            ->where('plan_id', $plan->id)
            ->with(['benefit:id,description,code', 'telemedicineServiceList:id,name,level'])
            ->orderBy('id')
            ->get();
    }
}
