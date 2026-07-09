<?php

declare(strict_types=1);

use App\Support\Filament\AdministrationPanelNavigationGroups;

it('grupos de navegacion de administracion inician colapsados', function (): void {
    $collapsedCount = collect(AdministrationPanelNavigationGroups::definitions())
        ->filter(fn ($group) => $group->isCollapsed())
        ->count();

    expect($collapsedCount)->toBe(count(AdministrationPanelNavigationGroups::labels()))
        ->and(AdministrationPanelNavigationGroups::labels())->toContain('ESTRUCTURA COMERCIAL', 'ADMINISTRACIÓN', 'NOMINA');
});

it('panel de administracion registra acordeon en sidebar', function (): void {
    $provider = file_get_contents(__DIR__.'/../../app/Providers/Filament/AdministrationPanelProvider.php');
    $script = file_get_contents(__DIR__.'/../../resources/views/filament/administration/partials/sidebar-navigation-accordion-script.blade.php');

    expect($provider)->toContain('AdministrationPanelNavigationGroups::definitions()')
        ->and($provider)->toContain('sidebar-navigation-accordion-script')
        ->and($provider)->toContain('PanelsRenderHook::SIDEBAR_NAV_END')
        ->and($script)->toContain('administrationNavigationAccordionV1');
});
