<?php

declare(strict_types=1);

use App\Support\Filament\MarketingPanelNavigationGroups;

it('grupos de navegacion de marketing inician colapsados, salvo DEL USUARIO que va primero y abierto', function (): void {
    $collapsedCount = collect(MarketingPanelNavigationGroups::definitions())
        ->filter(fn ($group) => $group->isCollapsed())
        ->count();

    expect($collapsedCount)->toBe(count(MarketingPanelNavigationGroups::labels()) - 1)
        ->and(MarketingPanelNavigationGroups::labels()[0])->toBe(App\Support\Filament\SharedNavigationGroups::USER)
        ->and(MarketingPanelNavigationGroups::definitions()[0]->isCollapsed())->toBeFalse()
        ->and(MarketingPanelNavigationGroups::labels())->toContain('AFILIACIONES', 'MARKETING', 'ZONA DE DESCARGA');
});

it('grupo MARKETING tiene icono en la navegacion', function (): void {
    $src = file_get_contents(__DIR__.'/../../app/Support/Filament/MarketingPanelNavigationGroups.php');

    expect($src)->toContain("->label('MARKETING')")
        ->and($src)->toContain("->icon('heroicon-o-megaphone')");
});

it('panel de marketing registra acordeon en sidebar', function (): void {
    $provider = file_get_contents(__DIR__.'/../../app/Providers/Filament/MarketingPanelProvider.php');
    $script = file_get_contents(__DIR__.'/../../resources/views/filament/marketing/partials/sidebar-navigation-accordion-script.blade.php');

    expect($provider)->toContain('MarketingPanelNavigationGroups::definitions()')
        ->and($provider)->toContain('sidebar-navigation-accordion-script')
        ->and($provider)->toContain('PanelsRenderHook::SIDEBAR_NAV_END')
        ->and($provider)->toContain('sidebarCollapsibleOnDesktop')
        ->and($script)->toContain('marketingNavigationAccordionV1');
});
