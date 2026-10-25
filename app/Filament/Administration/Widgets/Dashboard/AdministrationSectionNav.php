<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Administration\Pages\Dashboard;
use Filament\Widgets\Widget;

/**
 * Barra de accesos a las secciones del Escritorio: queda fija arriba al
 * desplazarse y marca la sección visible.
 */
class AdministrationSectionNav extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.administration.dashboard.section-nav';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['sections' => Dashboard::SECTIONS];
    }
}
