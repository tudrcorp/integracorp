<?php

declare(strict_types=1);

namespace App\Support\GuiaChat;

/**
 * Metadatos visuales para el menú de acciones «Quiero!» del GUIA-CHAT.
 */
final class ActionMenuOption
{
    /**
     * @param  array<string, array{label: string, description: string, short: string}>  $actionOptions
     * @return list<array{key: string, label: string, description: string, short: string, accent: string, icon: string}>
     */
    public static function enrich(array $actionOptions): array
    {
        $presentation = self::presentationCatalog();

        return collect($actionOptions)
            ->map(function (array $action, string $key) use ($presentation): array {
                $visual = $presentation[$key] ?? [
                    'accent' => 'from-slate-500/35 to-slate-400/15',
                    'icon' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z',
                ];

                return [
                    'key' => $key,
                    'label' => $action['label'],
                    'description' => $action['description'],
                    'short' => $action['short'],
                    'accent' => $visual['accent'],
                    'icon' => $visual['icon'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{accent: string, icon: string}>
     */
    private static function presentationCatalog(): array
    {
        return [
            'nuestros_planes' => [
                'accent' => 'from-cyan-500/35 to-sky-400/15',
                'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
            ],
            'registro_agencia_master' => [
                'accent' => 'from-indigo-500/35 to-blue-400/15',
                'icon' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z',
            ],
            'registro_agencia_general' => [
                'accent' => 'from-emerald-500/35 to-green-400/15',
                'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
            ],
            'registro_agente' => [
                'accent' => 'from-teal-500/35 to-cyan-400/15',
                'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
            ],
            'registro_subagente' => [
                'accent' => 'from-violet-500/35 to-purple-400/15',
                'icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
            ],
        ];
    }
}
