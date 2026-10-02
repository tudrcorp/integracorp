<?php

declare(strict_types=1);

namespace App\Support\Integracorp;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

/**
 * Resuelve qué módulos del hub puede ver y abrir un usuario autenticado.
 *
 * @phpstan-type HubModuleCard array{
 *     id: string,
 *     name: string,
 *     objective: string,
 *     image_url: string,
 *     url: string,
 *     badge: string,
 *     tags: list<string>,
 * }
 */
final class IntegracorpHubAccessibleModules
{
    /**
     * Paneles que no deben mostrarse como tarjeta en el hub (acceso solo por URL directa).
     *
     * @var list<string>
     */
    private const HIDDEN_FROM_HUB_PANEL_IDS = ['admin'];

    /**
     * Prioridad de destino para la tarjeta unificada "Agentes".
     *
     * @var list<string>
     */
    private const COMMERCIAL_NETWORK_PANEL_PRIORITY = ['master', 'general', 'agents'];

    /**
     * @return list<HubModuleCard>
     */
    public static function forUser(User $user): array
    {
        $modules = [];

        foreach (IntegracorpHubModuleRegistry::definitions() as $definition) {
            if (in_array($definition['id'], self::HIDDEN_FROM_HUB_PANEL_IDS, true)) {
                continue;
            }

            if ($definition['id'] === 'agents') {
                $commercial = self::resolveCommercialNetworkCard($user, $definition);
                if ($commercial !== null) {
                    $modules[] = $commercial;
                }

                continue;
            }

            if (! Route::has($definition['route'])) {
                continue;
            }

            $panel = self::resolvePanel($definition['id']);
            if ($panel === null) {
                continue;
            }

            if (! $user->canAccessPanel($panel)) {
                continue;
            }

            $modules[] = self::cardFromDefinition($definition, $definition['route']);
        }

        usort($modules, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $modules;
    }

    public static function userHasAnyModule(User $user): bool
    {
        return self::forUser($user) !== [];
    }

    /**
     * Una sola tarjeta "Agentes" para toda la red comercial.
     * Destino: master → general → agents (misma UI para internos y externos).
     *
     * @param  array{
     *     id: string,
     *     name: string,
     *     objective: string,
     *     image: string,
     *     route: string,
     *     sort: int,
     *     badge: string,
     *     tags: list<string>,
     * }  $definition
     * @return HubModuleCard|null
     */
    private static function resolveCommercialNetworkCard(User $user, array $definition): ?array
    {
        foreach (self::COMMERCIAL_NETWORK_PANEL_PRIORITY as $panelId) {
            $panel = self::resolvePanel($panelId);
            if ($panel === null || ! $user->canAccessPanel($panel)) {
                continue;
            }

            $route = self::dashboardRouteForPanel($panelId);
            if ($route === null || ! Route::has($route)) {
                continue;
            }

            return self::cardFromDefinition($definition, $route);
        }

        return null;
    }

    private static function dashboardRouteForPanel(string $panelId): ?string
    {
        return match ($panelId) {
            'master' => 'filament.master.pages.dashboard',
            'general' => 'filament.general.pages.dashboard',
            'agents' => 'filament.agents.pages.dashboard',
            default => null,
        };
    }

    /**
     * @param  array{
     *     id: string,
     *     name: string,
     *     objective: string,
     *     image: string,
     *     route: string,
     *     sort: int,
     *     badge: string,
     *     tags: list<string>,
     * }  $definition
     * @return HubModuleCard
     */
    private static function cardFromDefinition(array $definition, string $route): array
    {
        return [
            'id' => $definition['id'],
            'name' => $definition['name'],
            'objective' => $definition['objective'],
            'image_url' => self::publicAssetIfExists($definition['image']),
            'url' => route($route),
            'badge' => $definition['badge'],
            'tags' => $definition['tags'],
        ];
    }

    private static function publicAssetIfExists(string $relativePublicPath): string
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePublicPath), '/');

        if (is_file(public_path($normalized))) {
            return asset($normalized);
        }

        return asset('image/i2.jpg');
    }

    private static function resolvePanel(string $id): ?Panel
    {
        try {
            return Filament::getPanel($id);
        } catch (\Throwable) {
            return null;
        }
    }
}
