<?php

declare(strict_types=1);

namespace App\Support\Business\CapitationPlans;

use App\Enums\CapitationPaymentFrequency;
use App\Models\Benefit;
use App\Models\CapitationPlan;
use App\Models\Plan;
use App\Models\User;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Alta, edición y eliminación lógica de planes de capitado. Todo en una transacción y con
 * auditoría (antes y después). Las reglas viven aquí y no solo en el formulario.
 */
final class CapitationPlanWriter
{
    public const MAX_BENEFITS = 100;

    public const MAX_COST = 99999999.99;

    public const MIN_DELETION_REASON = 10;

    public const MAX_DELETION_REASON = 1000;

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(?CapitationPlan $plan, array $data, ?User $user): CapitationPlan
    {
        $attributes = self::attributes($plan, $data);
        $benefits = self::benefits($data['benefits'] ?? []);
        $coveredPlanIds = self::coveredPlanIds($data['covered_plan_ids'] ?? []);
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';

        $before = $plan !== null ? self::snapshot($plan) : null;

        $saved = DB::transaction(function () use ($plan, $attributes, $benefits, $coveredPlanIds, $user, $userName): CapitationPlan {
            self::assertNameIsFree($plan, $attributes);
            self::assertCoverageIsFree($plan, $attributes, $coveredPlanIds);

            $plan ??= new CapitationPlan([
                'created_by_user_id' => $user?->getKey(),
                'created_by_name' => $userName,
            ]);

            $plan->fill([
                ...$attributes,
                'updated_by_user_id' => $user?->getKey(),
                'updated_by_name' => $userName,
            ])->save();

            $plan->benefits()->delete();

            foreach ($benefits as $position => $benefit) {
                $plan->benefits()->create([...$benefit, 'sort' => $position]);
            }

            $plan->coveredPlans()->sync($coveredPlanIds);

            return $plan->fresh(['benefits', 'coveredPlans']) ?? $plan;
        });

        AffiliateCapitationCost::flush();

        SecurityAudit::log($before === null ? 'AUDIT_CAPITATION_PLAN_CREATED' : 'AUDIT_CAPITATION_PLAN_UPDATED', 'business.capitation-plans.save', [
            'capitation_plan_id' => $saved->getKey(),
            'provider' => $saved->providerKey(),
            'before' => $before,
            'after' => self::snapshot($saved),
        ], $user);

        return $saved;
    }

    /**
     * Eliminación lógica con razón obligatoria. La razón y el autor quedan en la fila y en la auditoría.
     *
     * @throws ValidationException si la razón es demasiado corta o el plan ya estaba eliminado.
     */
    public static function delete(CapitationPlan $plan, string $reason, ?User $user): void
    {
        $reason = Str::squish($reason);

        if (mb_strlen($reason) < self::MIN_DELETION_REASON || mb_strlen($reason) > self::MAX_DELETION_REASON) {
            throw ValidationException::withMessages([
                'reason' => 'Explique la razón de la eliminación (entre '.self::MIN_DELETION_REASON.' y '.self::MAX_DELETION_REASON.' caracteres).',
            ]);
        }

        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $snapshot = self::snapshot($plan);

        DB::transaction(function () use ($plan, $reason, $user, $userName): void {
            /** Releer con bloqueo: dos analistas eliminando a la vez no deben pisarse la razón. */
            $locked = CapitationPlan::query()->whereKey($plan->getKey())->lockForUpdate()->first();

            if (! $locked instanceof CapitationPlan) {
                throw ValidationException::withMessages(['reason' => 'Este plan ya fue eliminado por otra persona.']);
            }

            $locked->forceFill([
                'deletion_reason' => $reason,
                'deleted_by_user_id' => $user?->getKey(),
                'deleted_by_name' => $userName,
            ])->save();

            $locked->delete();
        });

        AffiliateCapitationCost::flush();

        SecurityAudit::log('AUDIT_CAPITATION_PLAN_DELETED', 'business.capitation-plans.delete', [
            'capitation_plan_id' => $plan->getKey(),
            'reason' => $reason,
            'deleted_by' => $userName,
            'plan' => $snapshot,
        ], $user);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function attributes(?CapitationPlan $plan, array $data): array
    {
        $errors = [];

        try {
            $provider = CapitationProviders::parse($data['provider_key'] ?? null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['data.provider_key' => $exception->getMessage()]);
        }

        $model = CapitationProviders::find($provider['type'], $provider['id']);
        $keepsProvider = $plan !== null && $plan->provider_type === $provider['type'] && (int) $plan->provider_id === $provider['id'];

        /** Un plan existente conserva su proveedor aunque luego salga de Outsourcing; uno nuevo no. */
        if ($model === null || (! $keepsProvider && ! CapitationProviders::isOutsourcing($model))) {
            $errors['data.provider_key'] = 'El proveedor elegido no pertenece a la red Outsourcing. Actívelo primero en su ficha de Operaciones.';
        }

        $name = Str::squish((string) ($data['name'] ?? ''));

        if ($name === '' || mb_strlen($name) > 150) {
            $errors['data.name'] = 'Escriba el nombre del plan (máximo 150 caracteres).';
        }

        $cost = is_numeric($data['cost_per_affiliate_usd'] ?? null) ? round((float) $data['cost_per_affiliate_usd'], 2) : null;

        if ($cost === null || $cost <= 0 || $cost > self::MAX_COST) {
            $errors['data.cost_per_affiliate_usd'] = 'El costo por afiliado debe ser mayor que cero.';
        }

        $frequency = CapitationPaymentFrequency::tryFrom((string) ($data['payment_frequency'] ?? ''));

        if ($frequency === null) {
            $errors['data.payment_frequency'] = 'Elija la frecuencia de pago.';
        }

        $from = filled($data['valid_from'] ?? null) ? CarbonImmutable::parse((string) $data['valid_from'])->startOfDay() : null;
        $until = filled($data['valid_until'] ?? null) ? CarbonImmutable::parse((string) $data['valid_until'])->startOfDay() : null;

        if ($from !== null && $until !== null && $until->lt($from)) {
            $errors['data.valid_until'] = 'La vigencia «hasta» no puede ser anterior a «desde».';
        }

        $negotiation = trim((string) ($data['negotiation_type'] ?? ''));

        if (mb_strlen($negotiation) < 10) {
            $errors['data.negotiation_type'] = 'Describa el tipo de negociación (mínimo 10 caracteres).';
        }

        $status = in_array($data['status'] ?? null, [CapitationPlan::STATUS_ACTIVE, CapitationPlan::STATUS_INACTIVE], true)
            ? (string) $data['status']
            : CapitationPlan::STATUS_ACTIVE;

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $documents = array_values(array_filter(array_map('strval', (array) ($data['documents'] ?? [])), fn (string $path): bool => $path !== ''));
        $names = array_intersect_key((array) ($data['document_names'] ?? []), array_flip($documents));

        return [
            'provider_type' => $provider['type'],
            'provider_id' => $provider['id'],
            'name' => $name,
            'cost_per_affiliate_usd' => $cost,
            'payment_frequency' => $frequency->value,
            'valid_from' => $from?->toDateString(),
            'valid_until' => $until?->toDateString(),
            'status' => $status,
            'negotiation_type' => $negotiation,
            'documents' => $documents,
            'document_names' => $names,
        ];
    }

    /**
     * @return list<array{benefit_id: int|null, name: string, note: string|null}>
     */
    private static function benefits(mixed $rows): array
    {
        $rows = is_array($rows) ? array_values($rows) : [];
        $catalog = Benefit::query()
            ->whereIn('id', array_filter(array_map(fn (mixed $row): int => (int) ($row['benefit_id'] ?? 0), $rows)))
            ->pluck('description', 'id');

        $benefits = [];
        $seen = [];

        foreach ($rows as $row) {
            $benefitId = (int) ($row['benefit_id'] ?? 0);
            $name = $benefitId > 0 && $catalog->has($benefitId)
                ? Str::squish((string) $catalog->get($benefitId))
                : Str::squish((string) ($row['custom_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $key = mb_strtoupper($name);

            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['data.benefits' => 'El beneficio «'.$name.'» está repetido en el plan.']);
            }

            $seen[$key] = true;
            $note = Str::squish((string) ($row['note'] ?? ''));

            $benefits[] = [
                'benefit_id' => $benefitId > 0 && $catalog->has($benefitId) ? $benefitId : null,
                'name' => mb_substr($name, 0, 255),
                'note' => $note !== '' ? mb_substr($note, 0, 255) : null,
            ];
        }

        if ($benefits === []) {
            throw ValidationException::withMessages(['data.benefits' => 'Agregue al menos un beneficio o servicio.']);
        }

        if (count($benefits) > self::MAX_BENEFITS) {
            throw ValidationException::withMessages(['data.benefits' => 'Un plan admite hasta '.self::MAX_BENEFITS.' beneficios.']);
        }

        return $benefits;
    }

    /**
     * Planes TDEC cubiertos: al menos uno, todos existentes. Sin esta relación el costo de
     * capitación de un afiliado no se puede resolver.
     *
     * @return list<int>
     */
    private static function coveredPlanIds(mixed $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($ids) ? $ids : []),
            fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            throw ValidationException::withMessages(['data.covered_plan_ids' => 'Elija al menos un plan TDEC que cubra este plan de capitado.']);
        }

        $existing = Plan::query()->whereKey($ids)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $missing = array_diff($ids, $existing);

        if ($missing !== []) {
            throw ValidationException::withMessages(['data.covered_plan_ids' => 'Alguno de los planes TDEC elegidos ya no existe. Recargue la página y vuelva a elegirlos.']);
        }

        sort($ids);

        return $ids;
    }

    /**
     * Un afiliado no puede quedar con dos costos de capitación del mismo proveedor: dos planes
     * activos del proveedor no pueden cubrir el mismo plan TDEC con vigencias que se crucen.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $coveredPlanIds
     */
    private static function assertCoverageIsFree(?CapitationPlan $plan, array $attributes, array $coveredPlanIds): void
    {
        if ($attributes['status'] !== CapitationPlan::STATUS_ACTIVE) {
            return;
        }

        $from = $attributes['valid_from'] ?? null;
        $until = $attributes['valid_until'] ?? null;

        $conflicts = CapitationPlan::query()
            ->forProvider($attributes['provider_type'], (int) $attributes['provider_id'])
            ->where('status', CapitationPlan::STATUS_ACTIVE)
            ->when($plan?->exists, fn ($query) => $query->whereKeyNot($plan->getKey()))
            ->whereHas('coveredPlans', fn ($query) => $query->whereIn('plans.id', $coveredPlanIds))
            /** Dos rangos [a, b] y [c, d] se cruzan si a <= d y c <= b; NULL es abierto. */
            ->when($until !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $until)))
            ->when($from !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $from)))
            ->with(['coveredPlans' => fn ($query) => $query->whereIn('plans.id', $coveredPlanIds)])
            ->lockForUpdate()
            ->get();

        if ($conflicts->isEmpty()) {
            return;
        }

        $first = $conflicts->first();

        throw ValidationException::withMessages([
            'data.covered_plan_ids' => 'El plan de capitado «'.$first->name.'» de este proveedor ya cubre '
                .$first->coveredPlans->pluck('description')->implode(', ')
                .' en una vigencia que se cruza. Ajuste las fechas, inactive uno de los dos o quite el plan TDEC repetido.',
        ]);
    }

    /**
     * Dos planes activos del mismo proveedor no pueden llamarse igual.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function assertNameIsFree(?CapitationPlan $plan, array $attributes): void
    {
        if ($attributes['status'] !== CapitationPlan::STATUS_ACTIVE) {
            return;
        }

        $taken = CapitationPlan::query()
            ->forProvider($attributes['provider_type'], (int) $attributes['provider_id'])
            ->where('status', CapitationPlan::STATUS_ACTIVE)
            ->whereRaw('UPPER(name) = ?', [mb_strtoupper($attributes['name'])])
            ->when($plan?->exists, fn ($query) => $query->whereKeyNot($plan->getKey()))
            ->lockForUpdate()
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['data.name' => 'Este proveedor ya tiene un plan activo con ese nombre.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshot(CapitationPlan $plan): array
    {
        $plan->loadMissing(['benefits', 'coveredPlans']);

        return [
            'provider' => $plan->providerKey(),
            'name' => $plan->name,
            'cost_per_affiliate_usd' => (string) $plan->cost_per_affiliate_usd,
            'payment_frequency' => $plan->payment_frequency?->value,
            'valid_from' => $plan->valid_from?->toDateString(),
            'valid_until' => $plan->valid_until?->toDateString(),
            'status' => $plan->status,
            'negotiation_type' => $plan->negotiation_type,
            'documents' => array_values((array) $plan->documents),
            'covered_plan_ids' => $plan->coveredPlans->modelKeys(),
            'benefits' => $plan->benefits->map(fn ($benefit): array => ['benefit_id' => $benefit->benefit_id, 'name' => $benefit->name, 'note' => $benefit->note])->all(),
        ];
    }
}
