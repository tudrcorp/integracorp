<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\CorporateAgendaSocialPlatformCatalog;

enum CorporateAgendaSocialPlatform: string
{
    case Instagram = 'instagram';
    case Youtube = 'youtube';
    case X = 'x';
    case Facebook = 'facebook';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $platform): array => [
                $platform->value => CorporateAgendaSocialPlatformCatalog::label($platform),
            ])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $platform): string => $platform->value,
            self::cases(),
        );
    }
}
