<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CorporateAgendaSocialPlatform;

final class CorporateAgendaSocialPlatformCatalog
{
    /**
     * @return array<string, array{
     *     label: string,
     *     short_label: string,
     *     chip_class: string,
     *     icon_ring_class: string,
     *     calendar_icon_class: string
     * }>
     */
    public static function metadata(): array
    {
        return [
            CorporateAgendaSocialPlatform::Instagram->value => [
                'label' => 'Instagram',
                'short_label' => 'IG',
                'chip_class' => 'border-pink-300/90 bg-gradient-to-br from-[#f58529]/20 via-[#dd2a7b]/20 to-[#8134af]/20 text-pink-800 dark:border-pink-300/55 dark:from-pink-500/30 dark:via-fuchsia-500/24 dark:to-violet-500/30 dark:text-pink-100',
                'icon_ring_class' => 'ring-pink-500/65 dark:ring-pink-300/70',
                'calendar_icon_class' => 'bg-gradient-to-br from-[#f58529] via-[#dd2a7b] to-[#8134af] text-white shadow-[0_6px_14px_rgba(221,42,123,0.35)] dark:shadow-[0_8px_16px_rgba(221,42,123,0.38)]',
            ],
            CorporateAgendaSocialPlatform::Youtube->value => [
                'label' => 'YouTube',
                'short_label' => 'YT',
                'chip_class' => 'border-red-300/90 bg-red-50 text-red-800 dark:border-red-300/55 dark:bg-red-500/22 dark:text-red-100',
                'icon_ring_class' => 'ring-red-500/65 dark:ring-red-300/70',
                'calendar_icon_class' => 'bg-[#FF0000] text-white shadow-[0_6px_14px_rgba(255,0,0,0.35)] dark:shadow-[0_8px_16px_rgba(255,0,0,0.38)]',
            ],
            CorporateAgendaSocialPlatform::X->value => [
                'label' => 'X (Twitter)',
                'short_label' => 'X',
                'chip_class' => 'border-slate-400/90 bg-slate-100 text-slate-900 dark:border-slate-300/60 dark:bg-slate-700/90 dark:text-white',
                'icon_ring_class' => 'ring-slate-500/70 dark:ring-slate-200/70',
                'calendar_icon_class' => 'bg-slate-900 text-white shadow-[0_6px_14px_rgba(15,23,42,0.35)] dark:bg-slate-100 dark:text-slate-900 dark:shadow-[0_8px_16px_rgba(255,255,255,0.25)]',
            ],
            CorporateAgendaSocialPlatform::Facebook->value => [
                'label' => 'Facebook',
                'short_label' => 'FB',
                'chip_class' => 'border-blue-300/90 bg-blue-50 text-blue-800 dark:border-blue-300/55 dark:bg-blue-500/22 dark:text-blue-100',
                'icon_ring_class' => 'ring-blue-500/65 dark:ring-blue-300/70',
                'calendar_icon_class' => 'bg-[#1877F2] text-white shadow-[0_6px_14px_rgba(24,119,242,0.35)] dark:shadow-[0_8px_16px_rgba(24,119,242,0.38)]',
            ],
        ];
    }

    public static function label(CorporateAgendaSocialPlatform $platform): string
    {
        return self::metadata()[$platform->value]['label'] ?? $platform->value;
    }

    /**
     * @return array{label: string, short_label: string, chip_class: string, icon_ring_class: string, calendar_icon_class: string}
     */
    public static function for(string $platformValue): array
    {
        return self::metadata()[$platformValue] ?? [
            'label' => $platformValue,
            'short_label' => strtoupper($platformValue),
            'chip_class' => 'border-slate-300/90 bg-slate-100 text-slate-800 dark:border-slate-500/60 dark:bg-slate-700/80 dark:text-slate-100',
            'icon_ring_class' => 'ring-slate-400/65 dark:ring-slate-300/70',
            'calendar_icon_class' => 'bg-slate-500 text-white shadow-[0_6px_14px_rgba(100,116,139,0.35)]',
        ];
    }
}
