<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Quién está usando la PWA: un visitante público o un agente con su
 * cuenta existente. El cliente final no inicia sesión.
 */
final class StorefrontAuth
{
    public static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function isAgent(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! self::isTruthyFlag($user->is_agent)) {
            return false;
        }

        if ($user->agent_id === null || (int) $user->agent_id <= 0) {
            return false;
        }

        return strtoupper(trim((string) $user->status)) === 'ACTIVO';
    }

    public static function currentIsAgent(): bool
    {
        return self::isAgent(self::user());
    }

    public static function agent(?User $user = null): ?Agent
    {
        $user ??= self::user();

        if (! self::isAgent($user) || $user === null) {
            return null;
        }

        $agent = Agent::query()->find((int) $user->agent_id);

        return $agent instanceof Agent ? $agent : null;
    }

    public static function displayName(?User $user = null): string
    {
        $user ??= self::user();

        if (! $user instanceof User) {
            return '';
        }

        $raw = trim((string) $user->name);
        $first = explode(' ', $raw)[0] ?? '';

        return $first !== '' ? $first : 'Agente';
    }

    public static function canLoginAsAgent(User $user): bool
    {
        return self::isAgent($user);
    }

    private static function isTruthyFlag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtoupper(trim((string) $value));

        return in_array($normalized, ['1', 'TRUE', 'SI', 'YES'], true);
    }
}
