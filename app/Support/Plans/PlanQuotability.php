<?php

declare(strict_types=1);

namespace App\Support\Plans;

use App\Enums\PlanQuotableScope;
use App\Models\Plan;
use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Quién puede habilitar un plan Dress Tylor para cotizar y en qué canal
 * (individual, corporativo o ambos). Los BÁSICOS no pasan por este filtro.
 */
final class PlanQuotability
{
    public const TYPE_BASICO = 'BASICO';

    public const TYPE_DRESS_TAILOR = 'DRESS-TAILOR';

    public const CHANNEL_INDIVIDUAL = 'individual';

    public const CHANNEL_CORPORATE = 'corporate';

    public static function isDressTylor(mixed $type): bool
    {
        return strtoupper(trim((string) $type)) === self::TYPE_DRESS_TAILOR;
    }

    public static function canConfigure(?User $user): bool
    {
        return $user instanceof User && UserNavigationAccess::isSuperAdmin($user);
    }

    public static function currentUserCanConfigure(): bool
    {
        $user = Auth::user();

        return $user instanceof User && self::canConfigure($user);
    }

    public static function resolveScope(mixed $value): ?PlanQuotableScope
    {
        if ($value instanceof PlanQuotableScope) {
            return $value;
        }

        return PlanQuotableScope::fromStored($value);
    }

    public static function isQuotableIn(Plan $plan, string $channel): bool
    {
        if (strtoupper(trim((string) $plan->status)) !== 'ACTIVO') {
            return false;
        }

        if (! self::isDressTylor($plan->type)) {
            return true;
        }

        if (! (bool) $plan->is_quotable) {
            return false;
        }

        $scope = self::resolveScope($plan->quotable_in);

        if ($scope === null) {
            return false;
        }

        return match ($channel) {
            self::CHANNEL_INDIVIDUAL => $scope->includesIndividual(),
            self::CHANNEL_CORPORATE => $scope->includesCorporate(),
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function scopeValuesForChannel(string $channel): array
    {
        return match ($channel) {
            self::CHANNEL_INDIVIDUAL => [
                PlanQuotableScope::Individual->value,
                PlanQuotableScope::Both->value,
            ],
            self::CHANNEL_CORPORATE => [
                PlanQuotableScope::Corporate->value,
                PlanQuotableScope::Both->value,
            ],
            default => [],
        };
    }

    /**
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public static function constrainToChannel(Builder $query, string $channel): Builder
    {
        $scopes = self::scopeValuesForChannel($channel);

        return $query
            ->where('status', 'ACTIVO')
            ->where(function (Builder $inner) use ($scopes): void {
                $inner->where('type', self::TYPE_BASICO)
                    ->orWhere(function (Builder $dress) use ($scopes): void {
                        $dress->where('type', self::TYPE_DRESS_TAILOR)
                            ->where('is_quotable', true)
                            ->whereIn('quotable_in', $scopes);
                    });
            });
    }

    /**
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public static function constrainToTypeAndChannel(Builder $query, ?string $type, string $channel): Builder
    {
        $normalized = self::isDressTylor($type)
            ? self::TYPE_DRESS_TAILOR
            : self::TYPE_BASICO;

        $query->where('type', $normalized)
            ->where('status', 'ACTIVO');

        if ($normalized === self::TYPE_DRESS_TAILOR) {
            $query->where('is_quotable', true)
                ->whereIn('quotable_in', self::scopeValuesForChannel($channel));
        }

        return $query;
    }

    /**
     * @return Builder<Plan>
     */
    public static function queryForIndividual(): Builder
    {
        return self::constrainToChannel(Plan::query(), self::CHANNEL_INDIVIDUAL);
    }

    /**
     * @return Builder<Plan>
     */
    public static function queryForCorporateType(?string $type): Builder
    {
        return self::constrainToTypeAndChannel(
            Plan::query(),
            $type,
            self::CHANNEL_CORPORATE,
        );
    }

    public static function optionLabel(Plan $plan): string
    {
        $description = filled($plan->description) ? (string) $plan->description : 'Plan #'.$plan->id;

        if (! self::isDressTylor($plan->type)) {
            return $description;
        }

        return $description.' (Dress Tylor)';
    }

    /**
     * @return Collection<int|string, string>
     */
    public static function optionsForIndividual(bool $includeMultiple = false): Collection
    {
        $options = self::queryForIndividual()
            ->orderBy('description')
            ->get(['id', 'description', 'type'])
            ->mapWithKeys(static fn (Plan $plan): array => [
                $plan->id => self::optionLabel($plan),
            ]);

        if ($includeMultiple) {
            $options->put('CM', 'COTIZACIÓN MULTIPLE');
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    public static function descriptionsForIndividual(): array
    {
        $descriptions = self::queryForIndividual()
            ->withCount('ageRanges')
            ->orderBy('description')
            ->get()
            ->mapWithKeys(static fn (Plan $plan): array => [
                $plan->id => $plan->age_ranges_count.' rango(s) de edad disponible(s).',
            ])
            ->all();

        $descriptions['CM'] = 'Seleccione más de dos (2) planes.';

        return $descriptions;
    }

    /**
     * @return Collection<int|string, string>
     */
    public static function optionsForCorporateType(?string $type, bool $includeMultiple = false): Collection
    {
        $options = self::queryForCorporateType($type)
            ->orderBy('description')
            ->get(['id', 'description', 'type'])
            ->mapWithKeys(static fn (Plan $plan): array => [
                $plan->id => self::optionLabel($plan),
            ]);

        if ($includeMultiple) {
            $options->put('CM', 'COTIZACIÓN MULTIPLE');
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    public static function descriptionsForCorporateType(?string $type): array
    {
        $descriptions = self::queryForCorporateType($type)
            ->withCount('ageRanges')
            ->orderBy('description')
            ->get()
            ->mapWithKeys(static fn (Plan $plan): array => [
                $plan->id => $plan->age_ranges_count.' rango(s) de edad disponible(s).',
            ])
            ->all();

        $descriptions['CM'] = 'Seleccione más de dos (2) planes.';

        return $descriptions;
    }

    public static function tableLabel(Plan $plan): string
    {
        if (! self::isDressTylor($plan->type)) {
            return 'Catálogo básico';
        }

        if (! (bool) $plan->is_quotable) {
            return 'No cotizable';
        }

        return self::resolveScope($plan->quotable_in)?->shortLabel() ?? 'No cotizable';
    }

    public static function tableColor(Plan $plan): string
    {
        if (! self::isDressTylor($plan->type)) {
            return 'gray';
        }

        if (! (bool) $plan->is_quotable) {
            return 'danger';
        }

        return self::resolveScope($plan->quotable_in)?->filamentColor() ?? 'danger';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{is_quotable: bool, quotable_in: string|null}
     */
    public static function attributesFromForm(array $data): array
    {
        $isQuotable = (bool) ($data['is_quotable'] ?? false);
        $scope = self::resolveScope($data['quotable_in'] ?? null);

        return [
            'is_quotable' => $isQuotable,
            'quotable_in' => $isQuotable ? ($scope?->value ?? PlanQuotableScope::Both->value) : null,
        ];
    }

    public static function normalizeOnSave(Plan $plan): void
    {
        if (! self::isDressTylor($plan->type)) {
            $plan->is_quotable = false;
            $plan->quotable_in = null;

            return;
        }

        $user = Auth::user();

        if (! self::canConfigure($user instanceof User ? $user : null)) {
            if ($plan->exists) {
                $plan->is_quotable = (bool) $plan->getOriginal('is_quotable');
                $originalScope = self::resolveScope($plan->getOriginal('quotable_in'));
                $plan->quotable_in = $originalScope;
            } else {
                $plan->is_quotable = false;
                $plan->quotable_in = null;
            }

            return;
        }

        $plan->is_quotable = (bool) $plan->is_quotable;

        if (! $plan->is_quotable) {
            $plan->quotable_in = null;

            return;
        }

        $plan->quotable_in = self::resolveScope($plan->quotable_in) ?? PlanQuotableScope::Both;
    }
}
