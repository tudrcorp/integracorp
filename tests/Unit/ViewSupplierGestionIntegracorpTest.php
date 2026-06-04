<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Pages\ViewSupplier;

it('expone estado y guardado livewire para gestion integracorp', function (): void {
    expect(property_exists(ViewSupplier::class, 'gestionIntegracorp'))->toBeTrue()
        ->and(method_exists(ViewSupplier::class, 'updatedGestionIntegracorp'))->toBeTrue();
});

it('renderiza la vista del check de gestion integracorp', function (): void {
    $html = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/suppliers/gestion-integracorp-tab.blade.php');
    $readonly = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/suppliers/gestion-integracorp-status-readonly.blade.php');

    expect($html)
        ->toContain('wire:model.live="gestionIntegracorp"')
        ->toContain('Habilitar gestión en Integracorp')
        ->toContain('integracorp-modules-panel')
        ->toContain('Funciones aceptadas')
        ->toContain('SUPERADMIN');

    expect($readonly)
        ->toContain('Estado de aceptación de funciones')
        ->toContain('disabled')
        ->toContain('integracorp-modules-panel');
});
