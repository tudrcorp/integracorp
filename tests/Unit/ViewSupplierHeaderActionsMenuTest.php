<?php

declare(strict_types=1);

it('usa el mismo menú de acciones iOS que las fichas de Negocios', function (): void {
    $pageContents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/ViewSupplier.php');

    expect($pageContents)
        ->toContain('FilamentIosActionsMenu::make([')
        ->toContain("FilamentIosButton::extraClassForFilamentColor('gray')")
        ->toContain("Action::make('back')")
        ->toContain("->label('Editar')")
        ->toContain("->label('Órdenes de servicio')")
        ->toContain("->label('Ficha Técnica del Proveedor')")
        ->toContain("->label('Agregar Carta de Aceptación')")
        ->toContain("->label('Ver Carta de Aceptación')")
        ->toContain("->label('Documentos de Afiliación')")
        // Las opciones del menú son simples, como en Negocios: sin estilo de botón propio.
        ->not->toContain('ActionGroup::make')
        ->not->toContain('aviso-btn-ios-primary')
        ->not->toContain('aviso-btn-ios-warning')
        ->not->toContain('supplier-actions-trigger')
        ->not->toContain('fi-supplier-actions-menu');
});
