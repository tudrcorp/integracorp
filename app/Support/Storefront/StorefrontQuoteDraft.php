<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\AgeRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

/**
 * Borrador de cotización en sesión. Cada página del flujo lee y escribe
 * aquí para que el avance se sienta como una app (páginas, no un form eterno).
 *
 * @phpstan-type PersonRow array{age: int, quantity: int}
 * @phpstan-type RangeRow array{age_range_id: int, total_persons: int}
 * @phpstan-type Draft array{
 *     plan_id: int|null,
 *     people: list<PersonRow>,
 *     ranges: list<RangeRow>,
 *     full_name: string,
 *     email: string,
 *     phone: string
 * }
 */
final class StorefrontQuoteDraft
{
    public const SESSION_KEY = 'storefront_quote_draft';

    /**
     * @return Draft
     */
    public static function empty(): array
    {
        return [
            'plan_id' => null,
            'people' => [],
            'ranges' => [],
            'full_name' => '',
            'email' => '',
            'phone' => '',
        ];
    }

    /**
     * @return Draft
     */
    public static function get(): array
    {
        $stored = Session::get(self::SESSION_KEY);

        if (! is_array($stored)) {
            return self::empty();
        }

        return array_merge(self::empty(), $stored);
    }

    /**
     * @param  Draft  $draft
     */
    public static function put(array $draft): void
    {
        Session::put(self::SESSION_KEY, array_merge(self::empty(), $draft));
    }

    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * @return Draft
     */
    public static function forPlan(int $planId): array
    {
        $draft = self::get();

        if ((int) ($draft['plan_id'] ?? 0) !== $planId) {
            $draft = self::empty();
            $draft['plan_id'] = $planId;
            self::put($draft);
        }

        return $draft;
    }

    /**
     * @param  list<PersonRow>  $people
     */
    public static function savePeople(int $planId, array $people): void
    {
        $draft = self::forPlan($planId);
        $draft['people'] = self::normalizePeople($people);
        $draft['ranges'] = [];
        self::put($draft);
    }

    /**
     * @param  list<RangeRow>  $ranges
     */
    public static function saveRanges(int $planId, array $ranges): void
    {
        $draft = self::forPlan($planId);
        $draft['ranges'] = self::normalizeRanges($ranges);
        $draft['people'] = [];
        self::put($draft);
    }

    public static function saveContact(int $planId, string $fullName, string $email, string $phone): void
    {
        $draft = self::forPlan($planId);
        $draft['full_name'] = mb_strtoupper(trim($fullName));
        $draft['email'] = mb_strtolower(trim($email));
        $draft['phone'] = preg_replace('/\s+/', '', trim($phone)) ?? '';
        self::put($draft);
    }

    /**
     * Agrupa personas por rango de edad, o usa los rangos que cargó el agente.
     *
     * @param  Collection<int, AgeRange>  $ageRanges
     * @return list<array{plan_id: int, age_range_id: int, total_persons: int}>
     */
    public static function entries(int $planId, Collection $ageRanges, bool $asAgent): array
    {
        $draft = self::forPlan($planId);

        if ($asAgent) {
            return self::entriesFromRanges($planId, $draft['ranges'], $ageRanges);
        }

        return self::entriesFromPeople($planId, $draft['people'], $ageRanges);
    }

    /**
     * @param  list<PersonRow>  $people
     * @return list<PersonRow>
     */
    public static function normalizePeople(array $people): array
    {
        $normalized = [];

        foreach ($people as $row) {
            if (! is_array($row)) {
                continue;
            }

            $age = (int) ($row['age'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($age < 0 || $age > 120 || $quantity < 1) {
                continue;
            }

            $normalized[] = [
                'age' => $age,
                'quantity' => min($quantity, 99),
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<RangeRow>  $ranges
     * @return list<RangeRow>
     */
    public static function normalizeRanges(array $ranges): array
    {
        $normalized = [];

        foreach ($ranges as $row) {
            if (! is_array($row)) {
                continue;
            }

            $ageRangeId = (int) ($row['age_range_id'] ?? 0);
            $totalPersons = (int) ($row['total_persons'] ?? 0);

            if ($ageRangeId <= 0 || $totalPersons < 1) {
                continue;
            }

            $normalized[] = [
                'age_range_id' => $ageRangeId,
                'total_persons' => min($totalPersons, 999),
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<PersonRow>  $people
     * @param  Collection<int, AgeRange>  $ageRanges
     * @return list<array{plan_id: int, age_range_id: int, total_persons: int}>
     */
    public static function entriesFromPeople(int $planId, array $people, Collection $ageRanges): array
    {
        $people = self::normalizePeople($people);

        if ($people === []) {
            throw ValidationException::withMessages([
                'people' => ['Agrega al menos una persona para cotizar.'],
            ]);
        }

        $grouped = [];

        foreach ($people as $person) {
            $range = self::matchAgeRange($ageRanges, $person['age']);

            if ($range === null) {
                throw ValidationException::withMessages([
                    'people' => [
                        sprintf('No hay tarifa para %d años en este plan.', $person['age']),
                    ],
                ]);
            }

            $id = (int) $range->getKey();
            $grouped[$id] = ($grouped[$id] ?? 0) + $person['quantity'];
        }

        $entries = [];

        foreach ($grouped as $ageRangeId => $totalPersons) {
            $entries[] = [
                'plan_id' => $planId,
                'age_range_id' => (int) $ageRangeId,
                'total_persons' => (int) $totalPersons,
            ];
        }

        return $entries;
    }

    /**
     * @param  list<RangeRow>  $ranges
     * @param  Collection<int, AgeRange>  $ageRanges
     * @return list<array{plan_id: int, age_range_id: int, total_persons: int}>
     */
    public static function entriesFromRanges(int $planId, array $ranges, Collection $ageRanges): array
    {
        $ranges = self::normalizeRanges($ranges);

        if ($ranges === []) {
            throw ValidationException::withMessages([
                'ranges' => ['Indica al menos una persona en un rango de edad.'],
            ]);
        }

        $validIds = $ageRanges
            ->filter(static fn (AgeRange $range): bool => (int) $range->plan_id === $planId)
            ->map(static fn (AgeRange $range): int => (int) $range->getKey())
            ->all();

        $entries = [];

        foreach ($ranges as $row) {
            if (! in_array($row['age_range_id'], $validIds, true)) {
                throw ValidationException::withMessages([
                    'ranges' => ['Hay un rango de edad que no pertenece a este plan.'],
                ]);
            }

            $entries[] = [
                'plan_id' => $planId,
                'age_range_id' => $row['age_range_id'],
                'total_persons' => $row['total_persons'],
            ];
        }

        return $entries;
    }

    /**
     * @param  Collection<int, AgeRange>  $ageRanges
     */
    public static function matchAgeRange(Collection $ageRanges, int $age): ?AgeRange
    {
        $matches = $ageRanges
            ->filter(static function (AgeRange $range) use ($age): bool {
                if ($range->age_init === null || $range->age_end === null) {
                    return false;
                }

                return (int) $range->age_init <= $age && (int) $range->age_end >= $age;
            })
            ->sortBy(static fn (AgeRange $range): int => (int) $range->age_end - (int) $range->age_init)
            ->values();

        $first = $matches->first();

        return $first instanceof AgeRange ? $first : null;
    }

    /**
     * @param  Draft  $draft
     */
    public static function hasContact(array $draft): bool
    {
        return filled($draft['full_name'] ?? null)
            && filled($draft['email'] ?? null)
            && filled($draft['phone'] ?? null);
    }
}
