<?php

declare(strict_types=1);

namespace App\Support\GuiaChat;

use Illuminate\Support\Facades\Route;

/**
 * Accesos de login INTEGRACORP reutilizables en GUIA-CHAT y pantallas públicas.
 */
final class IntegracorpLoginPanels
{
    /**
     * @return list<array{label: string, route: string, accent: string, icon: string, url: string}>
     */
    public static function forMenu(): array
    {
        $panels = [
            [
                'label' => 'AGENCIA MASTER',
                'route' => 'filament.master.auth.login',
                'accent' => 'from-indigo-500/30 to-blue-400/15',
                'icon' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z',
            ],
            [
                'label' => 'AGENCIA GENERAL',
                'route' => 'filament.general.auth.login',
                'accent' => 'from-emerald-500/30 to-green-400/15',
                'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
            ],
            [
                'label' => 'AGENTE',
                'route' => 'filament.agents.auth.login',
                'accent' => 'from-teal-500/30 to-cyan-400/15',
                'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
            ],
        ];

        return collect($panels)
            ->filter(static fn (array $panel): bool => Route::has($panel['route']))
            ->map(static fn (array $panel): array => [
                ...$panel,
                'url' => route($panel['route']),
            ])
            ->values()
            ->all();
    }
}
