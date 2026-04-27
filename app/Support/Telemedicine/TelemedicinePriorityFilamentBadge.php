<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

final class TelemedicinePriorityFilamentBadge
{
    public static function color(string $state): string
    {
        return match ($state) {
            'NO URGENTE', 'No Urgente' => 'no-urgente',
            'ESTANDAR', 'Estándar', 'ESTÁNDAR' => 'estandar',
            'URGENCIA', 'Urgencia' => 'urgencia',
            'EMERGENCIA', 'Emergencia' => 'emergencia',
            'CRITICO', 'Critico', 'Crítico' => 'critico',
            default => 'gray',
        };
    }

    public static function icon(string $state): string
    {
        return match ($state) {
            'NO URGENTE', 'No Urgente' => 'healthicons-f-health',
            'ESTANDAR', 'Estándar', 'ESTÁNDAR' => 'healthicons-f-health',
            'URGENCIA', 'Urgencia' => 'healthicons-f-health',
            'EMERGENCIA', 'Emergencia' => 'heroicon-c-shield-exclamation',
            'CRITICO', 'Critico', 'Crítico' => 'heroicon-c-shield-exclamation',
            default => 'healthicons-f-health',
        };
    }

    public static function recordRowClasses(?string $priorityName): string
    {
        if ($priorityName === null || $priorityName === '') {
            return 'border-l-4 border-gray-200 bg-gray-50/50 dark:border-gray-600 dark:bg-gray-950/20';
        }

        return match ($priorityName) {
            'NO URGENTE', 'No Urgente' => 'bg-[#005ca9]/10 dark:bg-[#005ca9]/25 border-l-4 border-[#005ca9]',
            'ESTANDAR', 'Estándar', 'ESTÁNDAR' => 'bg-[#02976d]/10 dark:bg-[#02976d]/25 border-l-4 border-[#02976d]',
            'URGENCIA', 'Urgencia' => 'bg-[#eab527]/10 dark:bg-[#eab527]/25 border-l-4 border-[#eab527]',
            'EMERGENCIA', 'Emergencia' => 'bg-[#f17f29]/10 dark:bg-[#f17f29]/25 border-l-4 border-[#f17f29]',
            'CRITICO', 'Critico', 'Crítico' => 'bg-[#e4003b]/10 dark:bg-[#e4003b]/25 border-l-4 border-[#e4003b]',
            default => 'border-l-4 border-gray-200 bg-gray-50/50 dark:border-gray-600 dark:bg-gray-950/20',
        };
    }
}
