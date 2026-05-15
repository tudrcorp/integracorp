<?php

declare(strict_types=1);

it('configura columna state_services con badges y lista legible', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Tables/SuppliersTable.php';
    $contents = file_get_contents($path);
    expect($contents)->toContain('state_services')
        ->toContain('listWithLineBreaks')
        ->toContain('expandableLimitedList')
        ->toContain("TextColumn::make('name')")
        ->toContain('min-w-52 sm:min-w-64 lg:min-w-72')
        ->toContain("TextColumn::make('razon_social')")
        ->toContain('lineClamp(2)')
        ->toContain('->tooltip(fn (Supplier $record): string => trim((string) $record->name))')
        ->toContain('->tooltip(fn (Supplier $record): string => trim((string) $record->razon_social))')
        ->toContain("->orderBy('suppliers.name'")
        ->toContain("->orderBy('supplier_sort_state_definition'")
        ->toContain("->orderBy('supplier_sort_city_definition'")
        ->toContain('modifyQueryUsing')
        ->toContain('$isResolvingRecord')
        ->toContain("leftJoin('states',")
        ->toContain("->where('suppliers.state_id'")
        ->toContain("->where('suppliers.city_id'");
});

it('supplier resource no aplica joins globales para evitar id ambiguo en vista y edición', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/SupplierResource.php';
    $contents = file_get_contents($path);
    expect($contents)->toContain("->with(['state', 'city'])")
        ->not->toContain("leftJoin('states',");
});

it('configura filtro Creado por con columna created_by y excluye filtro actualizado por (operaciones)', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Tables/SuppliersTable.php';
    $contents = file_get_contents($path);
    expect($contents)->toContain("SelectFilter::make('created_by')")
        ->toContain("->label('Creado por:')")
        ->not->toContain("SelectFilter::make('updated_by')")
        ->not->toContain('Actualizado por (Operaciones)');
});
