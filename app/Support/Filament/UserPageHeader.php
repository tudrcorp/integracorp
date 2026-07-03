<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

final class UserPageHeader
{
    public static function make(User $user, string $context = 'view'): string|Htmlable
    {
        $name = (string) ($user->name ?? 'Sin nombre');
        $status = strtoupper((string) ($user->status ?? 'SIN ESTADO'));
        $email = (string) ($user->email ?? 'Sin correo');
        $modules = self::formatDepartaments($user->departament);
        $profile = self::formatProfileLabel($user);
        $badgeStyle = self::badgeStyleForStatus($status);
        $heading = $context === 'edit' ? 'Editar usuario INTEGRACORP' : 'Usuario INTEGRACORP';

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:8px;padding:10px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
            .e($heading)
            .'</span>'
            .'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'
            .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">'
            .e($name)
            .'</span>'
            .'<span style="background-color: '.$badgeStyle['bg'].';color:#fff;padding:5px 14px;border-radius:999px;font-size:.78rem;font-weight:700;box-shadow:'.$badgeStyle['shadow'].';">'
            .e($status)
            .'</span>'
            .'</div>'
            .'<div style="display:flex;flex-direction:column;gap:4px;">'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">📧 '.e($email).'</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">🧩 '.e($modules).'</span>'
            .($profile !== null
                ? '<span class="text-sm font-medium text-primary-600 dark:text-primary-400">👤 '.e($profile).'</span>'
                : '')
            .'</div>'
            .'</div>'
        );
    }

    private static function formatDepartaments(mixed $departaments): string
    {
        if (! is_array($departaments) || $departaments === []) {
            return 'Sin módulos asignados';
        }

        return implode(', ', $departaments);
    }

    private static function formatProfileLabel(User $user): ?string
    {
        if ($user->is_agent) {
            return 'Agente comercial';
        }

        if ($user->is_subagent) {
            return 'Subagente';
        }

        if ($user->is_agency && $user->agency_type === 'MASTER') {
            return 'Agencia master';
        }

        if ($user->is_agency && $user->agency_type === 'GENERAL') {
            return 'Agencia general';
        }

        return null;
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private static function badgeStyleForStatus(string $status): array
    {
        return match ($status) {
            'ACTIVO' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'PENDIENTE' => ['bg' => '#f59e0b', 'shadow' => '0 8px 20px rgba(245,158,11,.35)'],
            'INACTIVO' => ['bg' => '#dc2626', 'shadow' => '0 8px 20px rgba(220,38,38,.35)'],
            default => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
        };
    }
}
