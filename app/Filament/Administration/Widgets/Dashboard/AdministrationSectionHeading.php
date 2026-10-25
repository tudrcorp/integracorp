<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use Filament\Widgets\Widget;

/**
 * Título de una sección del Escritorio, con su ancla para la barra de accesos.
 * Se configura con `make(['anchor' => …, 'title' => …, 'description' => …, 'icon' => …])`.
 */
class AdministrationSectionHeading extends Widget
{
    protected static bool $isDiscovered = false;

    /** Sin carga diferida: el ancla debe existir para que la barra de accesos salte a ella. */
    protected static bool $isLazy = false;

    protected string $view = 'filament.administration.dashboard.section-heading';

    protected int|string|array $columnSpan = 'full';

    public string $anchor = '';

    public string $title = '';

    public string $description = '';

    public string $icon = 'heroicon-o-squares-2x2';
}
