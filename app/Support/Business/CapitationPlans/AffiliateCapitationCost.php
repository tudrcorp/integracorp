<?php

declare(strict_types=1);

namespace App\Support\Business\CapitationPlans;

use App\Enums\CompanyAssociateStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\CompanyAssociate;
use Carbon\CarbonImmutable;

/**
 * Cuánto cuesta en capitación un afiliado: el `cost_per_affiliate_usd`, tal cual está
 * configurado, del plan de capitado del proveedor Outsourcing de su afiliación que cubre
 * el plan TDEC del afiliado.
 *
 * Es 0.0 cuando el afiliado o su afiliación no están activos, cuando la afiliación no lleva
 * un proveedor Outsourcing, o cuando ese proveedor no tiene un plan activo y vigente hoy
 * para el plan TDEC del afiliado. Si la afiliación lleva dos proveedores Outsourcing que
 * aplican, se suman: se paga capitación a ambos.
 *
 * Un afiliado puede tener un ajuste negociado (AffiliateCapitationOverride) que reemplaza
 * ese monto, pero solo cuando el plan le aplica: inactivo o sin plan sigue siendo 0.0.
 *
 * Un afiliado puede tener un plan de capitado asignado directamente
 * (AffiliateCapitationAssignment). Decisión de Gustavo (07/11/2026): reemplaza al cálculo
 * automático; el proveedor del plan DEBE estar en los proveedores de servicio de la afiliación
 * (no se exige que el plan cubra el plan TDEC); si el plan vence o se inactiva, o el proveedor
 * sale de la afiliación, da 0.0 con aviso (no vuelve en silencio a lo automático). El ajuste
 * negociado manda sobre él.
 *
 * Única fuente de verdad: tablas, exportes y reportes deben pasar por aquí.
 */
final class AffiliateCapitationCost
{
    public const ACTIVE_AFFILIATE = 'ACTIVO';

    public const ACTIVE_AFFILIATION = 'ACTIVA';

    /** @var list<string> */
    public const ACTIVE_ASSOCIATE_STATUSES = [
        CompanyAssociateStatus::Activo->value,
        CompanyAssociateStatus::ActivoSinVaucherIls->value,
    ];

    /**
     * @param  Affiliation|null  $affiliation  pásela cuando ya la tenga (relation manager) para no consultarla por fila
     */
    public static function forAffiliate(Affiliate $affiliate, ?Affiliation $affiliation = null, bool $applyOverride = true, ?\DateTimeInterface $on = null): CapitationCostResult
    {
        $affiliation ??= $affiliate->affiliation;

        if (! $affiliation instanceof Affiliation || $affiliate->trashed() || $affiliation->trashed()) {
            return CapitationCostResult::none('Sin afiliación activa');
        }

        return self::resolve(
            $affiliation->getAttribute('service_providers'),
            self::planId($affiliate->getAttribute('plan_id') ?? $affiliation->getAttribute('plan_id')),
            self::isActive($affiliate->getAttribute('status'), [self::ACTIVE_AFFILIATE])
                && self::isActive($affiliation->getAttribute('status'), [self::ACTIVE_AFFILIATION]),
            on: $on,
            subjectKey: self::subjectKey(CapitationSubject::AFFILIATE, $affiliate),
            applyOverride: $applyOverride,
        );
    }

    public static function forAffiliateCorporate(AffiliateCorporate $affiliate, ?AffiliationCorporate $affiliation = null, bool $applyOverride = true, ?\DateTimeInterface $on = null): CapitationCostResult
    {
        $affiliation ??= $affiliate->affiliationCorporate;

        if (! $affiliation instanceof AffiliationCorporate || $affiliate->trashed() || $affiliation->trashed()) {
            return CapitationCostResult::none('Sin afiliación activa');
        }

        return self::resolve(
            $affiliation->getAttribute('service_providers'),
            self::planId($affiliate->getAttribute('plan_id')),
            self::isActive($affiliate->getAttribute('status'), [self::ACTIVE_AFFILIATE])
                && self::isActive($affiliation->getAttribute('status'), [self::ACTIVE_AFFILIATION]),
            on: $on,
            subjectKey: self::subjectKey(CapitationSubject::AFFILIATE_CORPORATE, $affiliate),
            applyOverride: $applyOverride,
        );
    }

    /**
     * Nuevos Negocios: el proveedor sale de la empresa y el plan TDEC del plan de catálogo
     * de su generador de planes (o, en su defecto, del plan base del generador).
     */
    public static function forCompanyAssociate(CompanyAssociate $associate, bool $applyOverride = true, ?\DateTimeInterface $on = null): CapitationCostResult
    {
        $company = $associate->company;

        if ($company === null) {
            return CapitationCostResult::none('Sin empresa');
        }

        $generator = $company->planGenerator;

        return self::resolve(
            $company->getAttribute('service_providers'),
            self::planId($generator?->getAttribute('catalog_plan_id') ?? $generator?->getAttribute('plan_id')),
            self::isActive($associate->getAttribute('status'), self::ACTIVE_ASSOCIATE_STATUSES),
            on: $on,
            subjectKey: self::subjectKey(CapitationSubject::COMPANY_ASSOCIATE, $associate),
            applyOverride: $applyOverride,
        );
    }

    /**
     * @param  mixed  $serviceProviders  arreglo, JSON, texto separado por comas, vacío o nulo
     * @param  string|null  $subjectKey  `tipo:id` del afiliado, para leer su ajuste negociado
     * @param  bool  $applyOverride  false para obtener el monto del plan aunque haya ajuste
     */
    public static function resolve(
        mixed $serviceProviders,
        ?int $planId,
        bool $isActive,
        ?\DateTimeInterface $on = null,
        ?string $subjectKey = null,
        bool $applyOverride = true,
    ): CapitationCostResult {
        $index = self::index();
        $assignment = $index->assignmentFor($subjectKey);

        if (! $isActive) {
            return CapitationCostResult::none('Afiliado no activo', self::assignmentSummary($assignment));
        }

        if ($assignment !== null) {
            return self::resolveAssignment($assignment, $serviceProviders, $on ?? CarbonImmutable::now(), $index->overrideFor($subjectKey), $applyOverride);
        }

        $aliases = array_values(array_filter(self::aliases($serviceProviders), fn (string $alias): bool => $index->hasAlias($alias)));

        if ($aliases === []) {
            return CapitationCostResult::none('Sin proveedor Outsourcing');
        }

        if ($planId === null) {
            return CapitationCostResult::none('Sin plan TDEC asignado');
        }

        $on ??= CarbonImmutable::now();
        $lines = [];
        $ambiguous = [];

        foreach ($aliases as $alias) {
            $matches = array_values(array_filter(
                $index->plansFor($alias),
                fn (array $entry): bool => in_array($planId, $entry['plan_ids'], true) && $entry['plan_model']->isValidOn($on),
            ));

            /** El writer impide dos planes cruzados; si aun así existen, no se adivina cuál cobra. */
            if (count($matches) > 1) {
                $ambiguous[] = $matches[0]['provider'];

                continue;
            }

            if ($matches !== []) {
                $entry = $matches[0];
                unset($entry['plan_ids'], $entry['plan_model']);
                $lines[] = $entry;
            }
        }

        if ($lines === []) {
            return CapitationCostResult::none($ambiguous !== []
                ? 'Revise los planes de capitado de '.implode(', ', $ambiguous).': hay más de uno vigente para este plan'
                : 'El proveedor no tiene plan de capitado vigente para este plan');
        }

        $planAmount = round(array_sum(array_column($lines, 'cost')), 2);

        /** El ajuste negociado solo reemplaza al plan mientras el plan le aplique y el afiliado esté activo. */
        $override = $applyOverride ? $index->overrideFor($subjectKey) : null;

        return new CapitationCostResult(
            $override !== null ? $override['amount'] : $planAmount,
            $lines,
            $ambiguous !== [] ? 'Revise los planes de capitado de '.implode(', ', $ambiguous) : null,
            $planAmount,
            $override,
        );
    }

    /**
     * @param  array{capitation_plan_id: int, supplier_id: int, plan: string, provider: string, cost: float, frequency: string, alias: string, plan_model: \App\Models\CapitationPlan, usable: bool, reason: string, by: string, at: string}  $assignment
     * @param  array{amount: float, reason: string, by: string, at: string, plan_amount: float|null}|null  $override
     */
    private static function resolveAssignment(array $assignment, mixed $serviceProviders, \DateTimeInterface $on, ?array $override, bool $applyOverride): CapitationCostResult
    {
        $summary = self::assignmentSummary($assignment);

        if (! $assignment['usable'] || ! $assignment['plan_model']->isValidOn($on)) {
            return CapitationCostResult::none('Plan asignado «'.$assignment['plan'].'» vencido o inactivo: reasígnelo', $summary);
        }

        if ($assignment['alias'] === '' || ! in_array($assignment['alias'], self::aliases($serviceProviders), true)) {
            return CapitationCostResult::none('El proveedor del plan asignado ('.$assignment['provider'].') ya no está en la afiliación: reasígnelo', $summary);
        }

        $line = [
            'capitation_plan_id' => $assignment['capitation_plan_id'],
            'supplier_id' => $assignment['supplier_id'],
            'plan' => $assignment['plan'],
            'provider' => $assignment['provider'],
            'cost' => $assignment['cost'],
            'frequency' => $assignment['frequency'],
        ];

        $override = $applyOverride ? $override : null;

        return new CapitationCostResult(
            $override !== null ? $override['amount'] : $assignment['cost'],
            [$line],
            null,
            $assignment['cost'],
            $override,
            $summary,
        );
    }

    /**
     * @param  array{capitation_plan_id: int, plan: string, provider: string, reason: string, by: string, at: string}|array<string, mixed>|null  $assignment
     * @return array{capitation_plan_id: int, plan: string, provider: string, reason: string, by: string, at: string}|null
     */
    private static function assignmentSummary(?array $assignment): ?array
    {
        if ($assignment === null) {
            return null;
        }

        return [
            'capitation_plan_id' => (int) $assignment['capitation_plan_id'],
            'plan' => (string) $assignment['plan'],
            'provider' => (string) $assignment['provider'],
            'reason' => (string) $assignment['reason'],
            'by' => (string) $assignment['by'],
            'at' => (string) $assignment['at'],
        ];
    }

    /**
     * Proveedores de servicio de la afiliación de un afiliado (en Nuevos Negocios, de su empresa).
     */
    public static function serviceProvidersFor(\Illuminate\Database\Eloquent\Model $record): mixed
    {
        return match (true) {
            $record instanceof Affiliate => $record->affiliation?->getAttribute('service_providers'),
            $record instanceof AffiliateCorporate => $record->affiliationCorporate?->getAttribute('service_providers'),
            $record instanceof CompanyAssociate => $record->company?->getAttribute('service_providers'),
            default => null,
        };
    }

    /**
     * Descarta el índice del request actual (tras guardar un plan de capitado en el mismo request, o en tests).
     */
    public static function flush(): void
    {
        app()->forgetInstance(CapitationCostIndex::class);
    }

    /**
     * @return list<string>
     */
    public static function aliases(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            $decoded = $trimmed !== '' ? json_decode($trimmed, true) : [];
            $value = is_array($decoded) ? $decoded : explode(',', $trimmed);
        }

        if (! is_array($value)) {
            return [];
        }

        $aliases = [];

        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $alias = CapitationCostIndex::normalize((string) $item);

            if ($alias !== '') {
                $aliases[$alias] = $alias;
            }
        }

        return array_values($aliases);
    }

    private static function subjectKey(string $type, \Illuminate\Database\Eloquent\Model $record): ?string
    {
        return $record->exists && $record->getKey() !== null ? $type.':'.$record->getKey() : null;
    }

    private static function index(): CapitationCostIndex
    {
        $app = app();

        if (! $app->bound(CapitationCostIndex::class)) {
            $app->scoped(CapitationCostIndex::class, fn (): CapitationCostIndex => CapitationCostIndex::load());
        }

        return $app->make(CapitationCostIndex::class);
    }

    /**
     * @param  list<string>  $active
     */
    private static function isActive(mixed $status, array $active): bool
    {
        /** El estado puede venir casteado a enum (CompanyAssociateStatus) o como texto. */
        if ($status instanceof \BackedEnum) {
            $status = $status->value;
        }

        if (! is_scalar($status)) {
            return false;
        }

        return in_array(mb_strtoupper(trim((string) $status)), $active, true);
    }

    private static function planId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
