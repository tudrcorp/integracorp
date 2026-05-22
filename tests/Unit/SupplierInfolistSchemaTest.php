<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Schemas\SupplierInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de proveedor operations sin error', function (): void {
    $schema = Schema::make();
    $configured = SupplierInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('usa tabs y estilos alineados con infolist de agentes y agencias', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Schemas/SupplierInfolist.php');

    expect($source)
        ->toContain('supplierInfolistTabs')
        ->toContain('TABS_CONTAINER')
        ->toContain('SECTION_CARD')
        ->toContain('persistTab')
        ->toContain("Tab::make('Proveedor')")
        ->toContain("Tab::make('Órdenes de servicio')");
});
