<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

/**
 * Dónde un plan Dress Tylor puede cotizarse. Solo aplica cuando
 * `plans.is_quotable` es verdadero. Los BÁSICOS ignoran este valor.
 */
enum PlanQuotableScope: string
{
    use ResolvesFromMixedState;

    case Individual = 'individual';
    case Corporate = 'corporate';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Solo cotización individual',
            self::Corporate => 'Solo cotización corporativa',
            self::Both => 'Individual y corporativa',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Corporate => 'Corporativo',
            self::Both => 'Individual y corporativo',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Individual => 'info',
            self::Corporate => 'warning',
            self::Both => 'success',
        };
    }

    public function includesIndividual(): bool
    {
        return $this === self::Individual || $this === self::Both;
    }

    public function includesCorporate(): bool
    {
        return $this === self::Corporate || $this === self::Both;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $scope): array => [$scope->value => $scope->label()])
            ->all();
    }
}
