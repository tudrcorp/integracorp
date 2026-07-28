<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Schemas\SupplierInfolist;
use App\Models\Supplier;
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
        ->toContain("Tab::make('Órdenes de servicio')")
        ->toContain('SupplierIntegracorpManagementTab::make()')
        ->toContain('SupplierBeneficiaryBankingInfolist::bankingTab');

    $bankingSource = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierBeneficiaryBankingInfolist.php');

    expect($bankingSource)
        ->toContain('local_beneficiary_name')
        ->toContain('extra_beneficiary_swift');
});

it('agrupa la certificacion de infraestructura por categorias en el infolist', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Schemas/SupplierInfolist.php');

    expect($source)
        ->toContain("Tab::make('Infraestructura')")
        ->toContain("Section::make('Certificación de infraestructura')")
        ->toContain('infrastructureFieldsets()')
        ->toContain('SupplierInfrastructureCatalog::groups()')
        ->toContain("->columns(['default' => 2, 'sm' => 3, 'lg' => 4, 'xl' => 6])");
});

it('en infolist no muestra descripcion de infraestructura si no esta en si o esta vacia', function (): void {
    $method = new ReflectionMethod(SupplierInfolist::class, 'infraDescription');
    $method->setAccessible(true);

    $conDescripcionHuerfana = new Supplier([
        'cirugia_general' => false,
        'descripcion_cirugia_general' => 'Texto que no debe verse',
    ]);

    $sinDescripcion = new Supplier([
        'cirugia_general' => true,
        'descripcion_cirugia_general' => '   ',
    ]);

    $conDescripcion = new Supplier([
        'cirugia_general' => true,
        'descripcion_cirugia_general' => 'Quirófanos 24 h',
    ]);

    expect($method->invoke(null, $conDescripcionHuerfana, 'cirugia_general', 'descripcion_cirugia_general'))
        ->toBeNull()
        ->and($method->invoke(null, $sinDescripcion, 'cirugia_general', 'descripcion_cirugia_general'))
        ->toBeNull()
        ->and($method->invoke(null, $conDescripcion, 'cirugia_general', 'descripcion_cirugia_general'))
        ->toBe('Descripción: Quirófanos 24 h');
});
