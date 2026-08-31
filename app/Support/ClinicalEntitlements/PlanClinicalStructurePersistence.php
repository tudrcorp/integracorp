<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Models\BenefitClinicalSetting;
use App\Models\BenefitPlan;
use App\Models\Plan;
use App\Models\PlanBenefitClinicalSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Persiste SOLO el mapa clínico del plan. No toca fees, coberturas,
 * benefit_coverages ni cotizaciones.
 */
final class PlanClinicalStructurePersistence
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function persist(Plan $plan, array $rows, ?string $actor = null): void
    {
        $actor ??= (string) (Auth::user()?->name ?? 'sistema');
        $assigned = PlanClinicalCompleteness::assignedBenefitIds($plan);
        $assignedLookup = array_fill_keys($assigned, true);

        $normalized = [];

        foreach ($rows as $row) {
            $benefitId = (int) ($row['benefit_id'] ?? 0);
            if ($benefitId < 1) {
                continue;
            }

            if ($assigned !== [] && ! isset($assignedLookup[$benefitId])) {
                throw new InvalidArgumentException(
                    'El beneficio #'.$benefitId.' no pertenece a este plan. Asígnalo primero en la estructura comercial.'
                );
            }

            $normalized[$benefitId] = self::normalizeRow($row, $actor);
        }

        DB::transaction(static function () use ($plan, $normalized): void {
            $keep = array_keys($normalized);

            $stale = PlanBenefitClinicalSetting::query()->where('plan_id', $plan->id);

            if ($keep !== []) {
                $stale->whereNotIn('benefit_id', $keep);
            }

            $stale->delete();

            foreach ($normalized as $benefitId => $payload) {
                PlanBenefitClinicalSetting::query()->updateOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'benefit_id' => $benefitId,
                    ],
                    $payload,
                );
            }
        });
    }

    /**
     * Prefills from catalog defaults for benefits still missing a plan row.
     *
     * @return list<array<string, mixed>>
     */
    public static function hydrate(Plan $plan): array
    {
        $assigned = PlanClinicalCompleteness::assignedBenefitIds($plan);
        $planRows = PlanBenefitClinicalSetting::query()
            ->where('plan_id', $plan->id)
            ->get()
            ->keyBy('benefit_id');
        $defaults = BenefitClinicalSetting::query()
            ->whereIn('benefit_id', $assigned === [] ? [0] : $assigned)
            ->get()
            ->keyBy('benefit_id');

        $labels = BenefitPlan::query()
            ->where('plan_id', $plan->id)
            ->whereIn('benefit_id', $assigned === [] ? [0] : $assigned)
            ->pluck('description', 'benefit_id');

        $out = [];

        foreach ($assigned as $benefitId) {
            $row = $planRows->get($benefitId) ?? $defaults->get($benefitId);

            $out[] = [
                'benefit_id' => $benefitId,
                'benefit_label' => trim((string) ($labels->get($benefitId) ?? 'Beneficio #'.$benefitId)),
                'applies_clinically' => $row?->applies_clinically ?? true,
                'channel' => $row?->channel instanceof ClinicalServiceChannel
                    ? $row->channel->value
                    : ($row?->channel ?? null),
                'telemedicine_service_list_id' => $row?->telemedicine_service_list_id,
                'service_id' => $row?->service_id,
                'quota_scope' => $row?->quota_scope instanceof ClinicalQuotaScope
                    ? $row->quota_scope->value
                    : ($row?->quota_scope ?? null),
                'quota' => $row?->quota,
            ];
        }

        return $out;
    }

    /**
     * Mezcla el mapa clínico en el estado del asistente sin tocar tarifas.
     *
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public static function mergeIntoFormData(Plan $plan, array $formData): array
    {
        $clinical = [];
        foreach (self::hydrate($plan) as $row) {
            $clinical[(int) $row['benefit_id']] = $row;
        }

        foreach (['plan_benefits', 'package_benefits'] as $key) {
            $rows = (array) ($formData[$key] ?? []);
            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $benefitId = (int) ($row['benefit_id'] ?? 0);
                if ($benefitId < 1 || ! isset($clinical[$benefitId])) {
                    continue;
                }
                $formData[$key][$index] = array_merge($row, $clinical[$benefitId]);
            }
        }

        if (! isset($formData['package_benefits']) && isset($formData['package_benefit_ids'])) {
            $formData['package_benefits'] = [];
            foreach ((array) $formData['package_benefit_ids'] as $benefitId) {
                $id = (int) $benefitId;
                $formData['package_benefits'][] = $clinical[$id] ?? ['benefit_id' => $id, 'applies_clinically' => true];
            }
        }

        return $formData;
    }

    /**
     * Extrae filas clínicas del formulario del asistente (coberturas o paquete).
     *
     * @param  array<string, mixed>  $formData
     * @return list<array<string, mixed>>
     */
    public static function rowsFromPlanForm(array $formData): array
    {
        $rows = [];

        foreach (['plan_benefits', 'package_benefits'] as $key) {
            foreach ((array) ($formData[$key] ?? []) as $row) {
                if (! is_array($row) || ! filled($row['benefit_id'] ?? null)) {
                    continue;
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function persistBenefitDefault(int $benefitId, array $row, ?string $actor = null): BenefitClinicalSetting
    {
        $actor ??= (string) (Auth::user()?->name ?? 'sistema');
        $payload = self::normalizeRow($row, $actor);
        unset($payload['created_by']);

        return BenefitClinicalSetting::query()->updateOrCreate(
            ['benefit_id' => $benefitId],
            $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function normalizeRow(array $row, string $actor): array
    {
        $applies = filter_var($row['applies_clinically'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $channel = $applies ? ClinicalServiceChannel::fromStored($row['channel'] ?? null) : null;
        $scope = $applies ? ClinicalQuotaScope::fromStored($row['quota_scope'] ?? null) : null;

        $serviceListId = $channel?->usesTelemedicineServiceList()
            ? (filled($row['telemedicine_service_list_id'] ?? null) ? (int) $row['telemedicine_service_list_id'] : null)
            : null;

        $quota = null;
        if ($applies && $scope instanceof ClinicalQuotaScope && $scope->requiresQuota()) {
            $quota = max(1, (int) ($row['quota'] ?? 0));
        }

        return [
            'applies_clinically' => $applies,
            'channel' => $channel?->value,
            'telemedicine_service_list_id' => $serviceListId,
            'service_id' => filled($row['service_id'] ?? null) ? (int) $row['service_id'] : null,
            'quota_scope' => $scope?->value,
            'quota' => $quota,
            'updated_by' => $actor,
            'created_by' => $actor,
        ];
    }
}
