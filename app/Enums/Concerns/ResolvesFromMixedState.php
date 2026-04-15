<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * @mixin \BackedEnum
 */
trait ResolvesFromMixedState
{
    /**
     * Acepta el valor persistido (p. ej. ACTIVO), variantes en mayúsculas y etiquetas (p. ej. «Activo»).
     */
    public static function fromStored(mixed $value): ?static
    {
        if ($value instanceof static) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        $direct = static::tryFrom($s);
        if ($direct !== null) {
            return $direct;
        }

        $upper = mb_strtoupper($s);
        $fromUpper = static::tryFrom($upper);
        if ($fromUpper !== null) {
            return $fromUpper;
        }

        $spacesFromUnderscore = str_replace('_', ' ', $upper);
        $fromSpaced = static::tryFrom($spacesFromUnderscore);
        if ($fromSpaced !== null) {
            return $fromSpaced;
        }

        foreach (static::cases() as $case) {
            if (mb_strtolower($s, 'UTF-8') === mb_strtolower($case->label(), 'UTF-8')) {
                return $case;
            }
        }

        return static::legacyAliases(mb_strtolower($s));
    }

    protected static function legacyAliases(string $lower): ?static
    {
        return null;
    }

    public static function labelFromMixed(mixed $state): string
    {
        if ($state instanceof static) {
            return $state->label();
        }

        if (! is_string($state) || $state === '') {
            return '—';
        }

        $resolved = static::fromStored($state);

        return $resolved?->label() ?? $state;
    }

    public static function filamentColorFromMixed(mixed $state): string
    {
        if ($state instanceof static) {
            return $state->filamentColor();
        }

        if (! is_string($state) || $state === '') {
            return 'gray';
        }

        $resolved = static::fromStored($state);

        return $resolved?->filamentColor() ?? 'gray';
    }
}
