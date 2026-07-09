<?php

declare(strict_types=1);

use App\Support\Filament\OperationsPanelNavigationGroups;

it('grupos de navegacion de operaciones inician colapsados', function (): void {
    $collapsedCount = collect(OperationsPanelNavigationGroups::definitions())
        ->filter(fn ($group) => $group->isCollapsed())
        ->count();

    expect($collapsedCount)->toBe(count(OperationsPanelNavigationGroups::labels()))
        ->and(OperationsPanelNavigationGroups::labels())->toContain('AFILIADOS', 'TELEMEDICINA', 'ZONA DE DESCARGA');
});

it('panel de operaciones registra acordeon en sidebar', function (): void {
    $provider = file_get_contents(__DIR__.'/../../app/Providers/Filament/OperationsPanelProvider.php');
    $script = file_get_contents(__DIR__.'/../../resources/views/filament/operations/partials/sidebar-navigation-accordion-script.blade.php');

    expect($provider)->toContain('OperationsPanelNavigationGroups::definitions()')
        ->and($provider)->toContain('sidebar-navigation-accordion-script')
        ->and($provider)->toContain('PanelsRenderHook::SIDEBAR_NAV_END')
        ->and($script)->toContain('operationsNavigationAccordionV1');
});
