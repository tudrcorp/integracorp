<?php

declare(strict_types=1);

namespace App\Support\Business\CapitationPlans;

use App\Enums\CapitationPaymentFrequency;
use App\Models\Benefit;
use App\Models\CapitationPlan;
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

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(?CapitationPlan $plan, array $data, ?User $user): CapitationPlan
    {
        $attributes = self::attributes($plan, $data);
        $benefits = self::benefits($data['benefits'] ?? []);
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';

        $before = $plan !== null ? self::snapshot($plan) : null;

        $saved = DB::transaction(function () use ($plan, $attributes, $benefits, $user, $userName): CapitationPlan {
            self::assertNameIsFree($plan, $attributes);

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

            return $plan->fresh(['benefits']) ?? $plan;
        });

        SecurityAudit::log($before === null ? 'AUDIT_CAPITATION_PLAN_CREATED' : 'AUDIT_CAPITATION_PLAN_UPDATED', 'business.capitation-plans.save', [
            'capitation_plan_id' => $saved->getKey(),
            'provider' => $saved->providerKey(),
            'before' => $before,
            'after' => self::snapshot($saved),
        ], $user);

        return $saved;
    }

    public static function delete(CapitationPlan $plan, ?User $user): void
    {
        $snapshot = self::snapshot($plan);
        $plan->delete();

        SecurityAudit::log('AUDIT_CAPITATION_PLAN_DELETED', 'business.capitation-plans.delete', [
            'capitation_plan_id' => $plan->getKey(),
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
        $plan->loadMissing('benefits');

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
            'benefits' => $plan->benefits->map(fn ($benefit): array => ['benefit_id' => $benefit->benefit_id, 'name' => $benefit->name, 'note' => $benefit->note])->all(),
        ];
    }
}
