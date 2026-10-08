<?php

declare(strict_types=1);

use App\Support\Filament\BusinessPanelNavigationGroups;

it('grupos de navegacion de negocios inician colapsados, salvo DEL USUARIO que va primero y abierto', function (): void {
    $collapsedCount = collect(BusinessPanelNavigationGroups::definitions())
        ->filter(fn ($group) => $group->isCollapsed())
        ->count();

    expect($collapsedCount)->toBe(count(BusinessPanelNavigationGroups::labels()) - 1)
        ->and(BusinessPanelNavigationGroups::labels()[0])->toBe(App\Support\Filament\SharedNavigationGroups::USER)
        ->and(BusinessPanelNavigationGroups::definitions()[0]->isCollapsed())->toBeFalse()
        ->and(BusinessPanelNavigationGroups::labels())->toContain('ESTRUCTURA COMERCIAL', 'COTIZACIONES', 'CONFIGURACIÓN');
});

it('panel de negocios registra acordeon en sidebar', function (): void {
    $provider = file_get_contents(__DIR__.'/../../app/Providers/Filament/BusinessPanelProvider.php');
    $script = file_get_contents(__DIR__.'/../../resources/views/filament/business/partials/sidebar-navigation-accordion-script.blade.php');
    $sharedScript = file_get_contents(__DIR__.'/../../resources/views/filament/panels/partials/sidebar-navigation-accordion-script.blade.php');

    expect($provider)->toContain('BusinessPanelNavigationGroups::definitions()')
        ->and($provider)->toContain('sidebar-navigation-accordion-script')
        ->and($provider)->toContain('PanelsRenderHook::SIDEBAR_NAV_END')
        ->and($script)->toContain('businessNavigationAccordionV1')
        ->and($sharedScript)->toContain('toggleCollapsedGroup');
});
