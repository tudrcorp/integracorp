<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TdgCalendarOffice;

final class TdgCalendarOfficeCatalog
{
    /**
     * @return array<string, array{
     *     label: string,
     *     short_label: string,
     *     color: string,
     *     modifier: string,
     *     chip_class: string,
     *     dot_class: string
     * }>
     */
    public static function metadata(): array
    {
        return [
            TdgCalendarOffice::CentralLido->value => self::entry(TdgCalendarOffice::CentralLido, 'LIDO', '#06b6d4', 'central-lido'),
            TdgCalendarOffice::FarmadocLasDelicias->value => self::entry(TdgCalendarOffice::FarmadocLasDelicias, 'DEL', '#3b82f6', 'farmadoc-delicias'),
            TdgCalendarOffice::FarmadocSanBernardino->value => self::entry(TdgCalendarOffice::FarmadocSanBernardino, 'SBO', '#f97316', 'farmadoc-san-bernardino'),
        ];
    }

    /**
     * @return array{
     *     label: string,
     *     short_label: string,
     *     color: string,
     *     modifier: string,
     *     chip_class: string,
     *     dot_class: string
     * }
     */
    private static function entry(TdgCalendarOffice $office, string $shortLabel, string $color, string $modifier): array
    {
        return [
            'label' => $office->label(),
            'short_label' => $shortLabel,
            'color' => $color,
            'modifier' => $modifier,
            'chip_class' => "tdg-calendar-badge {$modifier}",
            'dot_class' => "tdg-office-chip__dot tdg-office-chip__dot--{$modifier}",
        ];
    }

    /**
     * @return array{label: string, short_label: string, color: string, modifier: string, chip_class: string, dot_class: string}
     */
    public static function for(string $office): array
    {
        return self::metadata()[$office] ?? [
            'label' => strtoupper($office),
            'short_label' => strtoupper(substr($office, 0, 3)),
            'color' => '#64748b',
            'modifier' => 'default',
            'chip_class' => 'tdg-calendar-badge default',
            'dot_class' => 'tdg-office-chip__dot tdg-office-chip__dot--default',
        ];
    }
}
