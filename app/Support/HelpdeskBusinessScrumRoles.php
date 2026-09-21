<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RrhhColaborador;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class HelpdeskBusinessScrumRoles
{
    public const PRODUCT_OWNER_NAME = 'Becky Acosta';

    /**
     * @var list<string>
     */
    public const DEVELOPER_NAMES = [
        'Anthony Aular',
        'Gustavo Camacho',
    ];

    public static function normalizeName(?string $name): string
    {
        $normalized = mb_strtoupper(trim((string) $name));

        return trim((string) preg_replace('/[^A-Z0-9]+/u', ' ', $normalized));
    }

    public static function matchesPerson(?string $fullName, string $expected): bool
    {
        $haystack = self::normalizeName($fullName);

        if ($haystack === '') {
            return false;
        }

        $tokens = preg_split('/\s+/', self::normalizeName($expected)) ?: [];

        foreach ($tokens as $token) {
            if ($token === '' || ! str_contains($haystack, $token)) {
                return false;
            }
        }

        return $tokens !== [];
    }

    public static function isProductOwnerName(?string $name): bool
    {
        return self::matchesPerson($name, self::PRODUCT_OWNER_NAME);
    }

    public static function isDeveloperName(?string $name): bool
    {
        foreach (self::DEVELOPER_NAMES as $developerName) {
            if (self::matchesPerson($name, $developerName)) {
                return true;
            }
        }

        return false;
    }

    public static function isProductOwnerUser(?Authenticatable $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if (self::isProductOwnerName($user->name)) {
            return true;
        }

        $colaborador = self::colaboradorForUser($user);

        return $colaborador !== null && self::isProductOwnerName($colaborador->fullName);
    }

    public static function isDeveloperUser(?Authenticatable $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if (self::isDeveloperName($user->name)) {
            return true;
        }

        $colaborador = self::colaboradorForUser($user);

        return $colaborador !== null && self::isDeveloperName($colaborador->fullName);
    }

    public static function productOwnerId(): ?int
    {
        return self::productOwnerColaborador()?->id;
    }

    public static function productOwnerColaborador(): ?RrhhColaborador
    {
        return self::findColaboradorByExpectedName(self::PRODUCT_OWNER_NAME);
    }

    /**
     * @return list<int>
     */
    public static function developerIds(): array
    {
        return self::developerColaboradores()
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, RrhhColaborador>
     */
    public static function developerColaboradores(): Collection
    {
        return collect(self::DEVELOPER_NAMES)
            ->map(static fn (string $name): ?RrhhColaborador => self::findColaboradorByExpectedName($name))
            ->filter()
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public static function developerOptions(): array
    {
        return self::developerColaboradores()
            ->mapWithKeys(static fn (RrhhColaborador $colaborador): array => [
                (int) $colaborador->id => (string) $colaborador->fullName,
            ])
            ->all();
    }

    public static function colaboradorForUser(User $user): ?RrhhColaborador
    {
        $userId = $user->getAuthIdentifier();

        if ($userId === null) {
            return null;
        }

        return RrhhColaborador::query()
            ->where('user_id', $userId)
            ->first();
    }

    private static function findColaboradorByExpectedName(string $expected): ?RrhhColaborador
    {
        $tokens = array_values(array_filter(preg_split('/\s+/', self::normalizeName($expected)) ?: []));

        if ($tokens === []) {
            return null;
        }

        $query = RrhhColaborador::query();

        foreach ($tokens as $token) {
            $query->where('fullName', 'like', '%'.$token.'%');
        }

        return $query
            ->orderBy('id')
            ->get(['id', 'fullName', 'user_id', 'emailCorporativo'])
            ->first(static fn (RrhhColaborador $colaborador): bool => self::matchesPerson($colaborador->fullName, $expected));
    }
}
