<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\Coverage;
use App\Models\IndividualQuote;
use App\Models\Plan;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Selección de coberturas de una cotización en la PWA.
 * El titular elige una o varias; el total es la suma, no el «Desde».
 *
 * @phpstan-type CoverageOption array{
 *     id: int,
 *     key: string,
 *     label: string,
 *     hint: string,
 *     annual: float,
 *     annual_label: string,
 *     persons: int,
 *     persons_label: string
 * }
 */
final class StorefrontQuoteCoverages
{
    public const QueryKey = 'c';

    /**
     * @param  Collection<int, mixed>  $details
     */
    public static function needsSelection(?Plan $plan, Collection $details): bool
    {
        return self::options($details, $plan) !== [];
    }

    /**
     * @param  Collection<int, mixed>  $details
     * @return list<CoverageOption>
     */
    public static function options(Collection $details, ?Plan $plan = null): array
    {
        if ($plan instanceof Plan && $plan->isBenefitPackage()) {
            return [];
        }

        $grouped = $details
            ->filter(static fn (mixed $line): bool => is_object($line) && (int) ($line->coverage_id ?? 0) > 0)
            ->groupBy(static fn (mixed $line): int => (int) $line->coverage_id);

        if ($grouped->isEmpty()) {
            return [];
        }

        $options = [];

        foreach ($grouped as $coverageId => $group) {
            /** @var Collection<int, mixed> $group */
            $first = $group->first();
            $annual = round((float) $group->sum(
                static fn (mixed $line): float => (float) ($line->subtotal_anual ?? 0)
            ), 2);
            $persons = (int) $group->sum(
                static fn (mixed $line): float => (int) ($line->total_persons ?? 0)
            );

            $options[] = [
                'id' => (int) $coverageId,
                'key' => 'cov-'.$coverageId,
                'label' => self::label($first),
                'hint' => $persons > 0
                    ? ($persons === 1 ? '1 persona cotizada' : $persons.' personas cotizadas')
                    : 'Según tu cotización',
                'annual' => $annual,
                'annual_label' => $annual > 0
                    ? StorefrontPlanNarrative::formatMoney($annual)
                    : 'Monto por confirmar',
                'persons' => $persons,
                'persons_label' => $persons === 1 ? '1 persona' : $persons.' personas',
                'sort' => self::coveragePrice($first),
            ];
        }

        usort($options, static fn (array $left, array $right): int => $left['sort'] <=> $right['sort']);

        return array_map(static function (array $option): array {
            unset($option['sort']);

            return $option;
        }, $options);
    }

    /**
     * @param  Collection<int, mixed>  $details
     * @return list<int>
     */
    public static function availableIds(Collection $details): array
    {
        return array_values(array_map(
            static fn (array $option): int => $option['id'],
            self::options($details),
        ));
    }

    /**
     * @param  list<int|string>|string|null  $raw
     * @param  list<int>  $available
     * @return list<int>
     */
    public static function sanitize(mixed $raw, array $available = []): array
    {
        $ids = [];

        if (is_string($raw)) {
            $raw = preg_split('/[,\s]+/', $raw) ?: [];
        }

        if (! is_array($raw)) {
            return [];
        }

        foreach ($raw as $value) {
            $id = (int) $value;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        if ($available === []) {
            return $ids;
        }

        return array_values(array_filter(
            $ids,
            static fn (int $id): bool => in_array($id, $available, true),
        ));
    }

    /**
     * @param  list<int>  $ids
     */
    public static function encode(array $ids): string
    {
        return implode(',', self::sanitize($ids));
    }

    /**
     * @param  list<int>  $available
     * @return list<int>
     */
    public static function decode(mixed $raw, array $available = []): array
    {
        return self::sanitize($raw, $available);
    }

    /**
     * @param  list<int>  $coverageIds
     * @return array<string, mixed>
     */
    public static function append(array $params, array $coverageIds): array
    {
        $encoded = self::encode($coverageIds);

        if ($encoded !== '') {
            $params[self::QueryKey] = $encoded;
        }

        return $params;
    }

    /**
     * @param  list<int>  $coverageIds
     */
    public static function route(string $name, array $params, array $coverageIds = []): string
    {
        return route($name, self::append($params, $coverageIds));
    }

    /**
     * @return list<int>
     */
    public static function fromRequest(): array
    {
        try {
            return self::sanitize(request()->query(self::QueryKey));
        } catch (Throwable) {
            return [];
        }
    }

    public static function hasRequestSelection(?string $code = null): bool
    {
        if (self::fromRequest() !== []) {
            return true;
        }

        $code = $code ?? self::requestCode();

        return $code !== '' && self::recall($code) !== [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function appendFromRequest(array $params): array
    {
        $ids = self::fromRequest();

        if ($ids === []) {
            $code = (string) ($params['code'] ?? self::requestCode());
            $ids = $code !== '' ? self::recall($code) : [];
        }

        return self::append($params, $ids);
    }

    /**
     * @return list<int>
     */
    public static function resolve(IndividualQuote $quote, ?Plan $plan = null): array
    {
        $available = self::availableIds($quote->detailsQuote);

        if (! self::needsSelection($plan, $quote->detailsQuote)) {
            return [];
        }

        $fromQuery = self::sanitize(self::fromRequest(), $available);

        if ($fromQuery !== []) {
            return $fromQuery;
        }

        return self::sanitize(self::recall((string) $quote->code), $available);
    }

    /**
     * null = hay que redirigir a la pantalla de coberturas.
     *
     * @return list<int>|null
     */
    public static function guard(IndividualQuote $quote, ?Plan $plan): ?array
    {
        if (! self::needsSelection($plan, $quote->detailsQuote)) {
            return [];
        }

        $ids = self::resolve($quote, $plan);

        return $ids === [] ? null : $ids;
    }

    /**
     * @param  list<int>  $ids
     */
    public static function remember(string $code, array $ids): void
    {
        $code = trim($code);

        if ($code === '') {
            return;
        }

        try {
            session()->put(self::sessionKey($code), self::sanitize($ids));
        } catch (Throwable) {
            //
        }
    }

    /**
     * @return list<int>
     */
    public static function recall(string $code): array
    {
        $code = trim($code);

        if ($code === '') {
            return [];
        }

        try {
            return self::sanitize(session(self::sessionKey($code)));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  Collection<int, mixed>  $details
     * @param  list<CoverageOption>  $options
     * @param  list<int>  $selected
     */
    public static function selectionLabel(array $options, array $selected): string
    {
        $wanted = self::sanitize($selected);
        $labels = [];

        foreach ($options as $option) {
            if (in_array($option['id'], $wanted, true)) {
                $labels[] = $option['label'];
            }
        }

        return implode(' · ', $labels);
    }

    public static function sessionKey(string $code): string
    {
        return 'storefront.quote.coverages.'.$code;
    }

    private static function requestCode(): string
    {
        try {
            return (string) (request()->route('code') ?? '');
        } catch (Throwable) {
            return '';
        }
    }

    private static function label(mixed $line): string
    {
        $price = self::coveragePrice($line);

        if ($price > 0) {
            return 'Cobertura '.StorefrontPlanNarrative::formatMoney($price);
        }

        return 'Cobertura';
    }

    private static function coveragePrice(mixed $line): float
    {
        if (! is_object($line)) {
            return 0.0;
        }

        $coverage = $line->coverage ?? null;

        if ($coverage instanceof Coverage) {
            return (float) $coverage->price;
        }

        return (float) ($line->fee ?? 0);
    }
}
