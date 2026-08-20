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
     * Fuera de un panel —consola, jobs— se evalúa contra el módulo dueño.
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
        $owner = BusinessFilamentActionPermissionRegistry::OWNER_MODULE;

        try {
            $panelId = Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return $owner;
        }

        if ($panelId === null) {
            return $owner;
        }

        $module = InternalPanelDepartmentMap::moduleForPanel($panelId);

        if ($module === null) {
            return $owner;
        }

        /** Si la acción no está habilitada en ese módulo, no se evalúa allí. */
        return BusinessFilamentActionPermissionRegistry::slugIsAvailableInModule($actionSlug, $module)
            ? $module
            : $owner;
    }
}
