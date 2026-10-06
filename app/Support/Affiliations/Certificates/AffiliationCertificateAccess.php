<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Models\AffiliationCertificateIssue;
use App\Models\User;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;

/**
 * Quién puede usar el generador y descargar un certificado: el mismo permiso que
 * abre el listado de afiliaciones (individuales o corporativas) en Negocios.
 */
final class AffiliationCertificateAccess
{
    public static function canUseFor(?User $user, string $affiliationType): bool
    {
        if ($user === null) {
            return false;
        }

        $resource = $affiliationType === AffiliationCertificateIssue::TYPE_CORPORATE
            ? AffiliationCorporateResource::class
            : AffiliationResource::class;

        $module = DepartmentNavigationPermissionRegistry::moduleFor($resource);

        if ($module === null) {
            return UserNavigationAccess::isSuperAdmin($user);
        }

        return UserNavigationAccess::canAccessMenuItem(
            $user,
            $module,
            DepartmentNavigationPermissionRegistry::slugsFor($resource),
        );
    }
}
