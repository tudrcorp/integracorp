<?php

declare(strict_types=1);

namespace App\Enums\ProjectManagement;

enum CeremonyType: string
{
    case Planning = 'planning';
    case Daily = 'daily';
    case Review = 'review';
    case Retro = 'retro';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planning',
            self::Daily => 'Daily',
            self::Review => 'Review',
            self::Retro => 'Retrospectiva',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
