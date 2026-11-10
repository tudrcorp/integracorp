<?php

declare(strict_types=1);

namespace App\Support\Affiliations\AffiliateFicha;

use App\Enums\AffiliateFichaScope;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\InternalPanelDepartmentMap;
use App\Support\Filament\UserNavigationAccess;
use Filament\Facades\Filament;
use Throwable;

/**
 * Quién puede generar la ficha del afiliado y con qué información.
 *
 * Vive en Negocios, Administración y Operaciones. Los datos de Administración
 * los ve cualquier analista del panel; los de operaciones y telemedicina son
 * clínicos y exigen el permiso {@see BusinessFilamentActionPermissionRegistry::VIEW_AFFILIATE_CLINICAL_FICHA}
 * en el módulo del panel desde el que se pide. Los usuarios de proveedor (que
 * también entran a Operaciones) no generan fichas.
 *
 * La misma regla se evalúa en la acción (qué opciones ve) y en la ruta del PDF
 * (qué se entrega): la URL firmada evita que se altere, pero no reemplaza la
 * autorización.
 */
final class AffiliateFichaAccess
{
    /** @var list<string> */
    public const PANELS = ['business', 'administration', 'operations'];

    public static function canOpen(?User $user, string $panelId): bool
    {
        if ($user === null || ! in_array($panelId, self::PANELS, true)) {
            return false;
        }

        if ($user->supplier_id !== null) {
            return false;
        }

        if (UserNavigationAccess::isSuperAdmin($user)) {
            return true;
        }

        $module = InternalPanelDepartmentMap::moduleForPanel($panelId);

        if ($module === null || ! UserNavigationAccess::userHasModule($user, $module)) {
            return false;
        }

        try {
            return $user->canAccessPanel(Filament::getPanel($panelId));
        } catch (Throwable) {
            return false;
        }
    }

    public static function canSeeClinical(?User $user, string $panelId): bool
    {
        if (! self::canOpen($user, $panelId)) {
            return false;
        }

        $module = (string) InternalPanelDepartmentMap::moduleForPanel($panelId);

        return UserNavigationAccess::canPerformModuleAction(
            $user,
            $module,
            BusinessFilamentActionPermissionRegistry::VIEW_AFFILIATE_CLINICAL_FICHA,
        );
    }

    public static function canUse(?User $user, string $panelId, AffiliateFichaScope $scope): bool
    {
        return $scope->includesClinical()
            ? self::canSeeClinical($user, $panelId)
            : self::canOpen($user, $panelId);
    }

    /**
     * @return list<AffiliateFichaScope>
     */
    public static function scopesFor(?User $user, string $panelId): array
    {
        if (! self::canOpen($user, $panelId)) {
            return [];
        }

        return self::canSeeClinical($user, $panelId)
            ? AffiliateFichaScope::cases()
            : [AffiliateFichaScope::Administration];
    }

    /**
     * Opción preseleccionada: la del área del panel, si el analista puede usarla.
     */
    public static function defaultScopeFor(?User $user, string $panelId): AffiliateFichaScope
    {
        $scopes = self::scopesFor($user, $panelId);
        $preferred = match ($panelId) {
            'operations' => AffiliateFichaScope::Clinical,
            'administration' => AffiliateFichaScope::Administration,
            default => AffiliateFichaScope::All,
        };

        return in_array($preferred, $scopes, true) ? $preferred : AffiliateFichaScope::Administration;
    }

    public static function currentPanelId(): ?string
    {
        try {
            $panelId = Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return null;
        }

        return in_array($panelId, self::PANELS, true) ? $panelId : null;
    }
}
