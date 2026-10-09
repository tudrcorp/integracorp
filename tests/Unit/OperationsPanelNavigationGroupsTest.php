<?php

declare(strict_types=1);

use App\Support\Filament\OperationsPanelNavigationGroups;

it('grupos de navegacion de operaciones inician colapsados, salvo DEL USUARIO que va primero y abierto', function (): void {
    $collapsedCount = collect(OperationsPanelNavigationGroups::definitions())
        ->filter(fn ($group) => $group->isCollapsed())
        ->count();

    expect($collapsedCount)->toBe(count(OperationsPanelNavigationGroups::labels()) - 1)
        ->and(OperationsPanelNavigationGroups::labels()[0])->toBe(App\Support\Filament\SharedNavigationGroups::USER)
        ->and(OperationsPanelNavigationGroups::definitions()[0]->isCollapsed())->toBeFalse()
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

it('grupo PROVEEDORES va justo después de AFILIADOS, plegado, con aliados, naturales y jurídicos en ese orden', function (): void {
    $labels = OperationsPanelNavigationGroups::labels();
    $groups = OperationsPanelNavigationGroups::definitions();
    $index = array_search(OperationsPanelNavigationGroups::PROVIDERS, $labels, true);

    expect(OperationsPanelNavigationGroups::PROVIDERS)->toBe('PROVEEDORES')
        ->and($index)->not->toBeFalse()
        ->and($labels[$index - 1])->toBe('AFILIADOS')
        ->and($groups[$index]->isCollapsed())->toBeTrue();

    $resources = [
        App\Filament\Operations\Resources\CorporateAllies\CorporateAllyResource::class,
        App\Filament\Operations\Resources\DoctorNurses\DoctorNurseResource::class,
        App\Filament\Operations\Resources\Suppliers\SupplierResource::class,
    ];

    foreach ($resources as $resource) {
        expect($resource::getNavigationGroup())->toBe(OperationsPanelNavigationGroups::PROVIDERS);
    }

    expect(array_map(fn (string $resource): ?int => $resource::getNavigationSort(), $resources))->toBe([5, 6, 7]);
});
