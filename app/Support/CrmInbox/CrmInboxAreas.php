<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

/**
 * Áreas que n8n puede mandar y en qué panel se leen.
 * Un caso sin área se ve en Negocios para que no quede invisible.
 */
final class CrmInboxAreas
{
    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'comercial' => 'Comercial',
        'administracion' => 'Administración',
        'operaciones' => 'Operaciones',
        'emergencias' => 'Emergencias',
        'viajes' => 'Viajes',
        'corporativo' => 'Corporativo',
        'aliados' => 'Aliados',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const BY_PANEL = [
        'business' => ['comercial', 'corporativo', 'aliados'],
        'operations' => ['operaciones', 'emergencias'],
        'administration' => ['administracion', 'viajes'],
    ];

    /**
     * @return list<string>
     */
    public static function forPanel(string $panel): array
    {
        return self::BY_PANEL[$panel] ?? [];
    }

    public static function label(?string $area): string
    {
        if ($area === null || $area === '') {
            return 'Sin área';
        }

        return self::LABELS[$area] ?? $area;
    }

    public static function panelFor(?string $area): string
    {
        if ($area === null || $area === '') {
            return 'business';
        }

        foreach (self::BY_PANEL as $panel => $areas) {
            if (in_array($area, $areas, true)) {
                return $panel;
            }
        }

        return 'business';
    }

    public static function departmentFor(?string $area): string
    {
        return match (self::panelFor($area)) {
            'operations' => 'OPERACIONES',
            'administration' => 'ADMINISTRACION',
            default => 'NEGOCIOS',
        };
    }

    /**
     * Áreas a las que se puede pasar un caso que todavía no está en esa área.
     *
     * @return list<array{area: string, label: string}>
     */
    public static function destinations(?string $current): array
    {
        $options = [];

        foreach (self::LABELS as $area => $label) {
            if ($area === $current) {
                continue;
            }

            $options[] = ['area' => $area, 'label' => $label];
        }

        return $options;
    }

    public static function destinationLabel(string $area, ?string $current): ?string
    {
        if ($area === $current || ! isset(self::LABELS[$area])) {
            return null;
        }

        return self::LABELS[$area];
    }
}
