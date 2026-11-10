<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource as AdministrationAffiliationCorporateResource;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource as AdministrationAffiliationResource;
use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource as BusinessAffiliationCorporateResource;
use App\Filament\Business\Resources\Affiliations\AffiliationResource as BusinessAffiliationResource;
use App\Models\User;
use Filament\Facades\Filament;
use Throwable;

/**
 * Quién puede ver o descargar la ficha técnica en PDF de una afiliación.
 *
 * Las rutas de la ficha solo piden sesión iniciada; sin esta guarda,
 * cualquier usuario autenticado (un agente, por ejemplo) podría bajar la ficha
 * de cualquier afiliación cambiando el número en la URL. Aquí se exige lo
 * mismo que para abrir la afiliación en su panel: entrar al panel (Negocios o
 * Administración, según la ruta) y tener acceso al recurso de afiliaciones.
 */
final class AffiliationFichaPdfAccess
{
    public const KIND_INDIVIDUAL = 'individual';

    public const KIND_CORPORATE = 'corporate';

    /**
     * @var array<string, array<string, class-string>>
     */
    private const RESOURCES = [
        'business' => [
            self::KIND_INDIVIDUAL => BusinessAffiliationResource::class,
            self::KIND_CORPORATE => BusinessAffiliationCorporateResource::class,
        ],
        'administration' => [
            self::KIND_INDIVIDUAL => AdministrationAffiliationResource::class,
            self::KIND_CORPORATE => AdministrationAffiliationCorporateResource::class,
        ],
    ];

    /**
     * Panel al que pertenece la ruta (`business.affiliations.ficha.preview` → business).
     * Cualquier otra ruta se trata como de Administración, la más antigua.
     */
    public static function panelForRoute(?string $routeName): string
    {
        return is_string($routeName) && str_starts_with($routeName, 'business.') ? 'business' : 'administration';
    }

    public static function userCanAccess(?User $user, string $kind, ?string $routeName): bool
    {
        if ($user === null) {
            return false;
        }

        $panelId = self::panelForRoute($routeName);
        $resource = self::RESOURCES[$panelId][$kind] ?? null;

        if ($resource === null) {
            return false;
        }

        try {
            if (! $user->canAccessPanel(Filament::getPanel($panelId))) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return $resource::canAccess();
    }
}
