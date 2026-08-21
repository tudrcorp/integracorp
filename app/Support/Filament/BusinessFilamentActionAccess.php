<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class BusinessFilamentActionAccess
{
    /**
     * Evalúa una acción granular contra el módulo del panel en el que se está.
     *
     * Un mismo recurso puede vivir en varios paneles (Empresas Aliadas está en
     * Negocios y en Administración) y el permiso se concede por módulo, igual que
     * el resto del sistema: tener la matriz de negociación en ADMINISTRACION no
     * la concede en NEGOCIOS.
     *
     * Fuera de un panel —rutas HTTP, consola, jobs— no hay panel Filament.
     * Si la acción vive en el módulo dueño se evalúa allí; si solo existe en
     * otro (el reporte de ventas, exclusivo de Administración) se usa ese.
     */
    public static function userCan(string $actionSlug): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        $module = self::currentModule($actionSlug);

        return UserNavigationAccess::canPerformModuleAction($user, $module, $actionSlug);
    }

    private static function currentModule(string $actionSlug): string
    {
        try {
            $panelId = Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return self::fallbackModule($actionSlug);
        }

        if ($panelId === null) {
            return self::fallbackModule($actionSlug);
        }

        $module = InternalPanelDepartmentMap::moduleForPanel($panelId);

        if ($module === null) {
            return self::fallbackModule($actionSlug);
        }

        /** Si la acción no está habilitada en ese módulo, no se evalúa allí. */
        return BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule($actionSlug, $module)
            ? $module
            : BusinessFilamentActionPermissionRegistry::OWNER_MODULE;
    }

    /**
     * Módulo contra el que se evalúa una acción cuando no hay panel Filament.
     */
    private static function fallbackModule(string $actionSlug): string
    {
        $owner = BusinessFilamentActionPermissionRegistry::OWNER_MODULE;
        $modules = BusinessFilamentActionPermissionRegistry::modulesForSlug($actionSlug);

        if (in_array($owner, $modules, true)) {
            return $owner;
        }

        return $modules[0] ?? $owner;
    }
}
