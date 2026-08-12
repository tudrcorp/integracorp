<?php

declare(strict_types=1);

it('encapsula las acciones de ficha jurídica en un menú desplegable conservando colores', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/ViewSupplier.php';
    $themePath = dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css';

    $pageContents = file_get_contents($pagePath);
    $themeContents = file_get_contents($themePath);

    expect($pageContents)
        ->toContain('ActionGroup::make')
        ->toContain("->label('Acciones')")
        ->toContain('fi-supplier-actions-menu')
        ->toContain('supplier-actions-trigger')
        ->toContain("->label('Editar')")
        ->toContain("->label('Ficha Técnica del Proveedor')")
        ->toContain("->label('Agregar Carta de Aceptación')")
        ->toContain("->label('Documentos de Afiliación')")
        ->toContain('aviso-btn-ios-primary')
        ->toContain('ticket-btn-ios')
        ->toContain('aviso-btn-ios-warning')
        ->toContain("->color('success')")
        ->toContain('justify-center')
        ->toContain("Action::make('back')");

    expect($themeContents)
        ->toContain('.fi-supplier-actions-menu .fi-dropdown-panel')
        ->toContain('max-width: 17.5rem')
        ->toContain('justify-content: center !important');
});
