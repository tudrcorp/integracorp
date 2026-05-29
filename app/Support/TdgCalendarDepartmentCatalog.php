<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TdgCalendarDepartment;

final class TdgCalendarDepartmentCatalog
{
    /**
     * @return array<string, array{
     *     label: string,
     *     short_label: string,
     *     color: string,
     *     modifier: string,
     *     chip_class: string,
     *     idle_chip_class: string,
     *     dot_class: string
     * }>
     */
    public static function metadata(): array
    {
        return [
            TdgCalendarDepartment::Comercial->value => self::entry(TdgCalendarDepartment::Comercial, 'COM', '#0284c7', 'comercial'),
            TdgCalendarDepartment::Afiliaciones->value => self::entry(TdgCalendarDepartment::Afiliaciones, 'AFI', '#059669', 'afiliaciones'),
            TdgCalendarDepartment::Proveedores->value => self::entry(TdgCalendarDepartment::Proveedores, 'PRO', '#d97706', 'proveedores'),
            TdgCalendarDepartment::Operaciones->value => self::entry(TdgCalendarDepartment::Operaciones, 'OPE', '#7c3aed', 'operaciones'),
            TdgCalendarDepartment::Marketing->value => self::entry(TdgCalendarDepartment::Marketing, 'MKT', '#db2777', 'marketing'),
            TdgCalendarDepartment::Proyecto->value => self::entry(TdgCalendarDepartment::Proyecto, 'PRY', '#4f46e5', 'proyecto'),
        ];
    }

    /**
     * @return array{
     *     label: string,
     *     short_label: string,
     *     color: string,
     *     modifier: string,
     *     chip_class: string,
     *     idle_chip_class: string,
     *     dot_class: string
     * }
     */
    private static function entry(TdgCalendarDepartment $department, string $shortLabel, string $color, string $modifier): array
    {
        $base = "tdg-dept-chip tdg-dept-chip--{$modifier}";

        return [
            'label' => $department->label(),
            'short_label' => $shortLabel,
            'display_label' => $shortLabel,
            'color' => $color,
            'modifier' => $modifier,
            'chip_class' => "{$base} is-selected",
            'idle_chip_class' => "{$base} is-idle",
            'dot_class' => "tdg-dept-chip__dot tdg-dept-chip__dot--{$modifier}",
        ];
    }

    /**
     * @return array{label: string, short_label: string, color: string, modifier: string, chip_class: string, idle_chip_class: string, dot_class: string}
     */
    public static function displayLabel(string $department): string
    {
        return self::for($department)['short_label'];
    }

    /**
     * @return array{label: string, short_label: string, color: string, modifier: string, chip_class: string, idle_chip_class: string, dot_class: string, display_label: string}
     */
    public static function for(string $department): array
    {
        $entry = self::metadata()[$department] ?? [
            'label' => strtoupper($department),
            'short_label' => strtoupper(substr($department, 0, 3)),
            'color' => '#64748b',
            'modifier' => 'default',
            'chip_class' => 'tdg-dept-chip tdg-dept-chip--default is-selected',
            'idle_chip_class' => 'tdg-dept-chip tdg-dept-chip--default is-idle',
            'dot_class' => 'tdg-dept-chip__dot tdg-dept-chip__dot--default',
        ];

        return [
            ...$entry,
            'display_label' => $entry['short_label'],
        ];
    }
}
