<?php

namespace App\Support;

use App\Models\Affiliation;
use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TarjetaAfiliacionQrPlanCatalog
{
    /**
     * @return array<int, string>
     */
    public static function individualSelectOptions(): array
    {
        $planIds = Affiliation::query()
            ->whereNotNull('plan_id')
            ->distinct()
            ->pluck('plan_id');

        return self::selectOptionsForPlanIds($planIds);
    }

    /**
     * @return array<int, string>
     */
    public static function corporateSelectOptions(): array
    {
        return Plan::query()
            ->orderBy('id')
            ->pluck('description', 'id')
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function allowedIndividualPlanIds(): array
    {
        return array_map('intval', array_keys(self::individualSelectOptions()));
    }

    /**
     * @return list<int>
     */
    public static function allowedCorporatePlanIds(): array
    {
        return array_map('intval', array_keys(self::corporateSelectOptions()));
    }

    /**
     * @param  Collection<int, mixed>  $planIds
     * @return array<int, string>
     */
    private static function selectOptionsForPlanIds(Collection $planIds): array
    {
        $ids = $planIds
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Plan::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->pluck('description', 'id')
            ->all();
    }

    public static function qrFilenameForPlanId(int $planId): string
    {
        return match ($planId) {
            1 => 'qr-plan-inicial.png',
            2 => 'qr-plan-ideal.png',
            3 => 'qr-plan-especial.png',
            default => 'qr-plan-'.$planId.'.png',
        };
    }

    public static function resolveQrFilename(?int $planId, ?string $planDescription = null): ?string
    {
        if ($planId !== null) {
            return self::qrFilenameForPlanId($planId);
        }

        $plan = self::findPlanByDescription($planDescription);

        if ($plan === null) {
            return self::legacyQrFilenameFromDescription($planDescription);
        }

        return self::qrFilenameForPlanId((int) $plan->id);
    }

    public static function displayTagForPlan(?int $planId, ?string $planDescription = null): string
    {
        $plan = $planId !== null
            ? Plan::query()->find($planId)
            : self::findPlanByDescription($planDescription);

        if ($plan !== null) {
            return match ((int) $plan->id) {
                1 => 'INICIAL',
                2 => 'IDEAL',
                3 => 'ESPECIAL',
                default => self::shortLabel((string) $plan->description),
            };
        }

        return self::legacyPlanTagFromDescription($planDescription);
    }

    public static function findPlanByDescription(?string $planDescription): ?Plan
    {
        $normalized = self::normalizeDescription($planDescription);

        if ($normalized === '') {
            return null;
        }

        return Plan::query()
            ->get(['id', 'description', 'code'])
            ->first(fn (Plan $plan): bool => self::normalizeDescription((string) $plan->description) === $normalized);
    }

    private static function normalizeDescription(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private static function shortLabel(string $description): string
    {
        $normalized = self::normalizeDescription($description);

        if (strlen($normalized) <= 22) {
            return $normalized;
        }

        return Str::upper(Str::limit($normalized, 22, ''));
    }

    private static function legacyQrFilenameFromDescription(?string $planDescription): ?string
    {
        $tag = self::legacyPlanTagFromDescription($planDescription);

        if ($tag === '') {
            return null;
        }

        return match ($tag) {
            'INICIAL' => 'qr-plan-inicial.png',
            'IDEAL' => 'qr-plan-ideal.png',
            'ESPECIAL' => 'qr-plan-especial.png',
            default => null,
        };
    }

    private static function legacyPlanTagFromDescription(?string $planDescription): string
    {
        $normalizedPlan = self::normalizeDescription($planDescription);

        return match ($normalizedPlan) {
            'PLAN INICIAL', 'INICIAL' => 'INICIAL',
            'PLAN IDEAL', 'IDEAL' => 'IDEAL',
            'PLAN ESPECIAL', 'ESPECIAL' => 'ESPECIAL',
            default => '',
        };
    }
}
