<?php

declare(strict_types=1);

namespace App\Support\Filament;

final class InternalPanelDepartmentMap
{
    /**
     * @var array<string, string>
     */
    private const PANEL_TO_MODULE = [
        'business' => 'NEGOCIOS',
        'administration' => 'ADMINISTRACION',
        'operations' => 'OPERACIONES',
        'marketing' => 'MARKETING',
        'projects' => 'PROYECTOS',
        'telemedicina' => 'TELEMEDICINA',
    ];

    /**
     * @var array<string, string>
     */
    private const NAMESPACE_TO_MODULE = [
        'App\\Filament\\Business\\' => 'NEGOCIOS',
        'App\\Filament\\Administration\\' => 'ADMINISTRACION',
        'App\\Filament\\Operations\\' => 'OPERACIONES',
        'App\\Filament\\Marketing\\' => 'MARKETING',
        'App\\Filament\\Projects\\' => 'PROYECTOS',
        'App\\Filament\\Telemedicina\\' => 'TELEMEDICINA',
    ];

    public static function moduleForPanel(string $panelId): ?string
    {
        return self::PANEL_TO_MODULE[$panelId] ?? null;
    }

    public static function moduleForClass(string $class): ?string
    {
        foreach (self::NAMESPACE_TO_MODULE as $namespace => $module) {
            if (str_starts_with($class, $namespace)) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function internalPanelIds(): array
    {
        return array_keys(self::PANEL_TO_MODULE);
    }
}
