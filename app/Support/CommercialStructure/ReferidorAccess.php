<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\InternalPanelDepartmentMap;
use App\Support\Filament\UserNavigationAccess;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class ReferidorAccess
{
    public const PERMISSION_SLUG = 'gestionar-referidor';

    /**
     * @var list<string>
     */
    private const MODULES = ['NEGOCIOS', 'ADMINISTRACION'];

    public static function userCanManage(): bool
    {
        try {
            $user = Auth::user();

            if ($user === null) {
                return false;
            }

            $slug = self::permissionSlug();

            foreach (self::modulesToEvaluate() as $module) {
                if (UserNavigationAccess::canPerformModuleAction($user, $module, $slug)) {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    public static function permissionSlug(): string
    {
        $constant = BusinessFilamentActionPermissionRegistry::class.'::MANAGE_REFERIDOR';

        if (defined($constant)) {
            return (string) constant($constant);
        }

        return self::PERMISSION_SLUG;
    }

    /**
     * @return list<string>
     */
    private static function modulesToEvaluate(): array
    {
        $detected = self::detectedPanelModule();

        if ($detected !== null) {
            return [$detected];
        }

        return self::MODULES;
    }

    private static function detectedPanelModule(): ?string
    {
        try {
            $panelId = Filament::getCurrentPanel()?->getId();

            if (! is_string($panelId) || $panelId === '') {
                return null;
            }

            $module = InternalPanelDepartmentMap::moduleForPanel($panelId);

            if (is_string($module) && in_array($module, self::MODULES, true)) {
                return $module;
            }
        } catch (Throwable) {
        }

        return null;
    }
}
