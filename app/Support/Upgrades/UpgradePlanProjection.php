<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Enums\PlanPricingMode;
use App\Models\AgeRange;
use App\Models\Benefit;
use App\Models\Coverage;
use App\Models\Plan;
use App\Models\Upgrade;
use App\Support\ClinicalEntitlements\PlanClinicalCompleteness;
use App\Support\Plans\PlanCodeGenerator;
use App\Support\Plans\PlanStructurePersistence;
use App\Support\SecurityAudit;
use InvalidArgumentException;

/**
 * Convierte un upgrade marcado «Es un plan» en un plan real, enlazado por
 * `plans.source_upgrade_id`. Cotización, afiliación, comisiones, renovación y telemedicina no
 * saben de upgrades: ven un plan más. La escritura va por {@see PlanStructurePersistence}, la
 * misma del asistente de planes, y el plan solo se edita desde el upgrade.
 *
 * - Un monto de cobertura → COBERTURAS; una sola cobertura sin monto → PAQUETE.
 * - Los servicios del upgrade son los beneficios del plan, sin costo límite.
 * - El plan solo queda ACTIVO si el upgrade está activo, sigue marcado y tiene el uso clínico
 *   completo ({@see self::refreshStatus()}). Al desmarcar pasa a INACTIVO: nada se borra.
 */
final class UpgradePlanProjection
{
    private const BASIC_PLAN_TYPE = 'BASICO';

    /**
     * Por qué el upgrade no puede ser un plan. Sin base de datos: la usa el validador.
     *
     * @param  array{plan_business_unit_id?: ?int, services: list<array{name: string}>, coverages: list<array{amount: ?float, ranges: list<array{age_from: ?int, age_to: ?int, price: ?float}>}>}  $data
     * @return list<string>
     */
    public static function errors(array $data): array
    {
        $errors = [];

        if (($data['plan_business_unit_id'] ?? null) === null) {
            $errors[] = 'Elija la unidad de negocio del plan.';
        }

        if ($data['services'] === []) {
            $errors[] = 'Para ser un plan necesita al menos un servicio: los servicios son sus beneficios.';
        }

        $coverages = $data['coverages'];

        if (count($coverages) > 1) {
            foreach ($coverages as $coverage) {
                if ($coverage['amount'] === null) {
                    $errors[] = 'Para ser un plan, con varias coberturas todas deben tener monto.';

                    break;
                }
            }
        }

        $reference = null;

        foreach ($coverages as $index => $coverage) {
            $spans = self::spans($coverage['ranges']);
            $reference ??= $spans;

            if ($spans !== $reference) {
                $errors[] = 'Para ser un plan, todas las coberturas deben tener los mismos rangos de edad. Revise '.UpgradeStructureValidator::coverageLabel($coverage['amount'], $index).'.';

                break;
            }
        }

        return $errors;
    }

    /**
     * Requisitos del plan en palabras del analista, para verlos mientras llena el formulario.
     *
     * @param  array<string, mixed>  $data  estado del formulario
     * @return list<array{label: string, detail: string, state: 'done'|'missing'|'later'}>
     */
    public static function checklist(array $data, ?Plan $plan = null): array
    {
        $data = UpgradeStructureValidator::normalize($data);
        $services = count(array_filter($data['services'], static fn (array $service): bool => $service['name'] !== ''));
        $coverages = array_values(array_filter($data['coverages'], static fn (array $coverage): bool => $coverage['ranges'] !== []));
        $problem = null;

        foreach (self::errors([...$data, 'plan_business_unit_id' => 1, 'services' => [['name' => 'x']]]) as $error) {
            $problem = $error;

            break;
        }

        $clinicalDone = $plan !== null && $plan->exists && PlanClinicalCompleteness::isComplete($plan);

        return [
            [
                'label' => 'Unidad de negocio',
                'detail' => $data['plan_business_unit_id'] !== null ? 'Elegida.' : 'Elíjala aquí arriba.',
                'state' => $data['plan_business_unit_id'] !== null ? 'done' : 'missing',
            ],
            [
                'label' => 'Servicios',
                'detail' => $services > 0
                    ? $services.($services === 1 ? ' servicio pasará a ser beneficio del plan.' : ' servicios pasarán a ser beneficios del plan.')
                    : 'Agregue al menos uno en «Servicios incluidos».',
                'state' => $services > 0 ? 'done' : 'missing',
            ],
            [
                'label' => 'Coberturas y rangos de edad',
                'detail' => match (true) {
                    $coverages === [] => 'Agregue una cobertura con sus rangos y precios.',
                    $problem !== null => $problem,
                    default => 'Todas las coberturas comparten los mismos rangos.',
                },
                'state' => $coverages !== [] && $problem === null ? 'done' : 'missing',
            ],
            [
                'label' => 'Uso clínico',
                'detail' => $clinicalDone ? 'Completo.' : 'Después de guardar. Hasta entonces el plan no se puede cotizar.',
                'state' => $clinicalDone ? 'done' : 'later',
            ],
        ];
    }

    /**
     * Crea o actualiza el plan del upgrade. Corre dentro de la transacción del catálogo: si algo
     * falla no queda ni el plan ni el cambio del upgrade.
     *
     * @throws InvalidArgumentException
     */
    public static function sync(Upgrade $upgrade, string $actor): Plan
    {
        $upgrade->load(['activeServices', 'activeCoverages.activeAgeRanges']);

        $name = (string) $upgrade->name;
        $plan = self::planOf($upgrade);

        $duplicate = Plan::query()
            ->where('description', $name)
            ->when($plan !== null, static fn ($query) => $query->whereKeyNot($plan->getKey()))
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('Ya existe un plan llamado «'.$name.'». Cambie el nombre del upgrade para no confundirlos al cotizar.');
        }

        $isNew = $plan === null;
        $coverages = $upgrade->activeCoverages->sortBy(fn ($coverage): float => (float) ($coverage->amount ?? 0))->values();
        $isPackage = $coverages->count() === 1 && $coverages->first()->amount === null;
        $mode = $isPackage ? PlanPricingMode::Paquete : PlanPricingMode::Coberturas;

        $plan ??= new Plan([
            'code' => PlanCodeGenerator::next(),
            'status' => 'INACTIVO',
            'created_by' => $actor,
        ]);

        $plan->fill([
            'description' => $name,
            'business_unit_id' => $upgrade->plan_business_unit_id,
            'type' => self::BASIC_PLAN_TYPE,
            'pricing_mode' => $mode->value,
            'structure_version' => Plan::STRUCTURE_VERSION_WIZARD,
        ]);
        $plan->source_upgrade_id = $upgrade->getKey();
        $plan->save();

        $benefitIds = self::benefitIds($upgrade, $actor);

        PlanStructurePersistence::persist($plan, $isPackage
            ? self::packageState($plan, $coverages->first(), $benefitIds)
            : self::coverageState($plan, $coverages, $benefitIds));

        $status = self::refreshStatus($plan);

        SecurityAudit::log($isNew ? 'AUDIT_BUSINESS_UPGRADE_PLAN_CREATED' : 'AUDIT_BUSINESS_UPGRADE_PLAN_SYNCED', 'business.upgrades.plan', [
            'panel' => 'business',
            'module' => 'upgrades',
            'upgrade_id' => $upgrade->getKey(),
            'upgrade_code' => $upgrade->code,
            'plan_id' => $plan->getKey(),
            'plan_code' => $plan->code,
            'pricing_mode' => $mode->value,
            'status' => $status,
        ]);

        return $plan;
    }

    /**
     * Estatus que le toca al plan según su upgrade y su uso clínico. Es lo único que lo cambia.
     */
    public static function refreshStatus(Plan $plan): string
    {
        $upgrade = $plan->source_upgrade_id !== null ? Upgrade::query()->find($plan->source_upgrade_id) : null;
        $sellable = $upgrade !== null && $upgrade->is_plan && $upgrade->isActive() && PlanClinicalCompleteness::isComplete($plan);
        $status = $sellable ? 'ACTIVO' : 'INACTIVO';

        if ($plan->status !== $status) {
            $plan->forceFill(['status' => $status])->save();
        }

        return $status;
    }

    public static function refreshStatusOfPlanId(mixed $planId): void
    {
        $plan = is_numeric($planId) ? Plan::query()->whereNotNull('source_upgrade_id')->find((int) $planId) : null;

        if ($plan !== null) {
            self::refreshStatus($plan);
        }
    }

    public static function planOf(Upgrade $upgrade): ?Plan
    {
        return Plan::query()->where('source_upgrade_id', $upgrade->getKey())->first();
    }

    /**
     * Qué le falta al plan para poder cotizarse, en palabras del analista.
     *
     * @return list<string>
     */
    public static function pendingLabels(Plan $plan): array
    {
        $missing = PlanClinicalCompleteness::missingBenefitLabels($plan);

        return $missing !== [] ? $missing : (PlanClinicalCompleteness::isComplete($plan) ? [] : ['Sin beneficios asignados']);
    }

    /**
     * @return list<int>
     */
    private static function benefitIds(Upgrade $upgrade, string $actor): array
    {
        $ids = [];

        foreach ($upgrade->activeServices as $service) {
            $description = mb_strtoupper(trim((string) $service->name), 'UTF-8');
            $benefit = Benefit::query()->where('description', $description)->where('status', 'ACTIVO')->first()
                ?? Benefit::query()->create(['description' => $description, 'status' => 'ACTIVO', 'created_by' => $actor]);

            $ids[] = (int) $benefit->getKey();
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $benefitIds
     * @return array<string, mixed>
     */
    private static function packageState(Plan $plan, mixed $coverage, array $benefitIds): array
    {
        $rows = [];

        foreach ($coverage->activeAgeRanges->sortBy('age_from') as $range) {
            $rows[] = [...self::ageRangeRow($plan, (int) $range->age_from, (int) $range->age_to), 'rate' => (string) $range->price];
        }

        return [
            'pricing_mode' => PlanPricingMode::Paquete->value,
            'package_benefit_ids' => $benefitIds,
            'package_age_ranges' => $rows,
        ];
    }

    /**
     * @param  iterable<int, mixed>  $coverages
     * @param  list<int>  $benefitIds
     * @return array<string, mixed>
     */
    private static function coverageState(Plan $plan, iterable $coverages, array $benefitIds): array
    {
        $planCoverages = [];
        $limits = [];
        $ranges = [];

        foreach ($coverages as $index => $coverage) {
            $key = 'upgrade-coverage-'.$index;
            $price = number_format((float) $coverage->amount, 2, '.', '');
            $planCoverages[] = [
                'id' => Coverage::query()->where('plan_id', $plan->getKey())->where('price', $price)->value('id'),
                'coverage_key' => $key,
                'price' => $price,
            ];
            $limits[] = ['coverage_key' => $key, 'limit' => null];

            foreach ($coverage->activeAgeRanges as $range) {
                $span = (int) $range->age_from.'-'.(int) $range->age_to;
                $ranges[$span] ??= self::ageRangeRow($plan, (int) $range->age_from, (int) $range->age_to) + ['rates' => []];
                $ranges[$span]['rates'][] = ['coverage_key' => $key, 'rate' => (string) $range->price];
            }
        }

        uasort($ranges, static fn (array $a, array $b): int => $a['age_init'] <=> $b['age_init']);

        return [
            'pricing_mode' => PlanPricingMode::Coberturas->value,
            'plan_coverages' => $planCoverages,
            'plan_benefits' => array_map(static fn (int $benefitId): array => ['benefit_id' => $benefitId, 'limits' => $limits], $benefitIds),
            'plan_age_ranges' => array_values($ranges),
        ];
    }

    /**
     * Fila de rango. Un rango que ya existe se reutiliza: las afiliaciones apuntan a su id.
     *
     * @return array{id: mixed, range: string, age_init: int, age_end: int}
     */
    private static function ageRangeRow(Plan $plan, int $from, int $to): array
    {
        return [
            'id' => AgeRange::query()->where('plan_id', $plan->getKey())->where('age_init', $from)->where('age_end', $to)->value('id'),
            'range' => $from.' a '.$to,
            'age_init' => $from,
            'age_end' => $to,
        ];
    }

    /**
     * @param  list<array{age_from: ?int, age_to: ?int, price: ?float}>  $ranges
     * @return list<string>
     */
    private static function spans(array $ranges): array
    {
        $spans = array_map(static fn (array $range): string => $range['age_from'].'-'.$range['age_to'], $ranges);
        sort($spans);

        return $spans;
    }
}
