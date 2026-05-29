<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

final class ProjectManagementProjectAppearance
{
    public const DEFAULT_COLOR = '#3B82F6';

    public const DEFAULT_ICON = 'heroicon-o-folder';

    /**
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            'heroicon-o-folder' => 'Carpeta',
            'heroicon-o-rocket-launch' => 'Lanzamiento',
            'heroicon-o-bolt' => 'Rayo',
            'heroicon-o-fire' => 'Fuego',
            'heroicon-o-sparkles' => 'Destacado',
            'heroicon-o-check-badge' => 'Completado',
            'heroicon-o-chart-bar' => 'Analítica',
            'heroicon-o-cog-6-tooth' => 'Operaciones',
            'heroicon-o-users' => 'Equipo',
            'heroicon-o-building-office-2' => 'Corporativo',
            'heroicon-o-globe-alt' => 'Global',
            'heroicon-o-flag' => 'Prioridad',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function colorPresets(): array
    {
        return [
            '#3B82F6' => 'Azul',
            '#8B5CF6' => 'Morado',
            '#10B981' => 'Verde',
            '#F59E0B' => 'Ámbar',
            '#EF4444' => 'Rojo',
            '#06B6D4' => 'Cian',
            '#EC4899' => 'Rosa',
            '#64748B' => 'Gris',
        ];
    }
}
