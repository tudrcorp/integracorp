<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Support\Filament\Operations\OperationsPanelHomeFallback;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * Escritorio del panel de Operaciones.
 *
 * Reemplaza a Filament\Pages\Dashboard para que su visibilidad se administre
 * como cualquier otro ítem de menú (permiso "escritorio" del módulo OPERACIONES),
 * conservando la ruta filament.operations.pages.dashboard y los widgets del panel.
 */
class Dashboard extends BaseDashboard
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Escritorio';

    protected static ?string $title = 'Escritorio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -2;

    /**
     * El Escritorio es la URL raíz del panel: si el usuario no lo tiene asignado
     * se le lleva a su primer módulo disponible en lugar de mostrarle un 403.
     */
    public function mountCanAuthorizeAccess(): void
    {
        if (static::canAccess()) {
            return;
        }

        $fallbackUrl = OperationsPanelHomeFallback::url();

        if ($fallbackUrl === null) {
            abort(403);
        }

        $this->redirect($fallbackUrl);
    }
}
