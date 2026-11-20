<?php

declare(strict_types=1);

use App\Support\Farmadoc\FarmadocMedicationList;

function farmadocTableSource(string $relativePath): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
}

/**
 * @return list<array<string, mixed>>
 */
function farmadocTableCatalog(): array
{
    return [
        ['product_id' => 1, 'name' => 'Amoxicilina', 'requires_prescription' => true, 'is_controlled_substance' => false, 'concentration' => '500 mg', 'presentation' => 'Caja x 10', 'brand' => 'Genven'],
        ['product_id' => 2, 'name' => 'Clonazepam', 'requires_prescription' => true, 'is_controlled_substance' => true, 'concentration' => null, 'presentation' => '  ', 'brand' => null],
        ['product_id' => 3, 'name' => 'Acetaminofén', 'requires_prescription' => false, 'is_controlled_substance' => false],
    ];
}

it('filtra por fórmula, controlado y los del pedido, combinables', function (?array $filters, array $ordered, array $expected): void {
    $result = FarmadocMedicationList::applyFilters(farmadocTableCatalog(), $filters, $ordered);

    expect(array_column($result, 'product_id'))->toBe($expected);
})->with([
    'sin filtros' => [null, [], [1, 2, 3]],
    'requiere fórmula' => [['requires_prescription' => ['isActive' => true]], [], [1, 2]],
    'controlado' => [['controlled' => ['isActive' => true]], [], [2]],
    'solo los del pedido' => [['in_order' => ['isActive' => true]], [3, 1], [1, 3]],
    'pedido vacío' => [['in_order' => ['isActive' => true]], [], []],
    'combinados' => [['requires_prescription' => ['isActive' => true], 'in_order' => ['isActive' => true]], [2, 3], [2]],
    'filtro apagado' => [['controlled' => ['isActive' => false]], [], [1, 2, 3]],
    'estado mal formado' => [['controlled' => 'sí', 'otro' => ['isActive' => true]], [], [1, 2, 3]],
]);

it('arma la segunda línea del medicamento y solo marca las condiciones que importan', function (): void {
    [$amoxicillin, $clonazepam, $acetaminophen] = farmadocTableCatalog();

    expect(FarmadocMedicationList::detailsLine($amoxicillin))->toBe('500 mg · Caja x 10 · Genven')
        ->and(FarmadocMedicationList::detailsLine($clonazepam))->toBeNull()
        ->and(FarmadocMedicationList::conditionLabels($clonazepam))->toBe(['Requiere fórmula', 'Controlado'])
        ->and(FarmadocMedicationList::conditionLabels($acetaminophen))->toBe([]);
});

it('muestra menos columnas: medicamento con detalle, SKU opcional y una sola columna de condiciones', function (): void {
    $page = farmadocTableSource('app/Filament/Operations/Pages/FarmadocMedicationOrder.php');

    expect($page)
        ->toContain('FarmadocMedicationList::detailsLine($record)')
        ->toContain("TextColumn::make('conditions')")
        ->toContain('->toggleable(isToggledHiddenByDefault: true)')
        ->not->toContain("TextColumn::make('formula_label')")
        ->not->toContain("TextColumn::make('controlled_label')")
        ->not->toContain("TextColumn::make('concentration')")
        ->not->toContain("TextColumn::make('presentation')");
});

it('ofrece filtros inmediatos, resalta las filas del pedido y explica los estados vacíos', function (): void {
    $page = farmadocTableSource('app/Filament/Operations/Pages/FarmadocMedicationOrder.php');

    expect($page)
        ->toContain("Filter::make('requires_prescription')")
        ->toContain("Filter::make('controlled')")
        ->toContain("Filter::make('in_order')")
        ->toContain('->deferFilters(false)')
        ->toContain('layout: FiltersLayout::Dropdown')
        ->not->toContain('FiltersLayout::AboveContent')
        ->toContain('FarmadocMedicationList::applyFilters($this->medications(), $filters, $this->orderedProductIds())')
        ->toContain("'fi-farmadoc-row-in-order'")
        ->toContain('Aún no hay medicamentos en el pedido')
        ->toContain("->searchDebounce('400ms')");
});

it('cambia la cantidad al instante y la confirma con el servidor en orden, sin bloquear la tabla', function (): void {
    $stepper = farmadocTableSource('resources/views/filament/operations/columns/farmadoc-quantity-stepper.blade.php');

    expect($stepper)
        ->toContain('Agregar')
        ->toContain('x-on:click="set(1)"')
        ->toContain('$wire.changeLineQuantity(this.productId, value)')
        ->toContain('if (this.inFlight || this.pending === null)')
        ->toContain('return this.pending ?? this.serverQty;')
        ->toContain('$wire.orderLines')
        ->toContain('type="number"')
        ->not->toContain('wire:loading.attr="disabled"')
        ->not->toContain('addToOrder');
});

it('usa estilos propios en la vista y suma las unidades en la barra del pedido', function (): void {
    $view = farmadocTableSource('resources/views/filament/operations/pages/farmadoc-medication-order.blade.php');

    expect($view)
        ->toContain('.fi-farmadoc-row-in-order > td')
        ->toContain('.dark .fi-farmadoc-row-in-order > td')
        ->toContain('.fi-farmadoc-stepper')
        ->toContain('fi-farmadoc-order-bar-units')
        ->toContain('$catalogNotice');
});

it('no deja al analista sin catálogo si Farmadoc no responde', function (): void {
    $page = farmadocTableSource('app/Filament/Operations/Pages/FarmadocMedicationOrder.php');

    expect($page)
        ->toContain('STALE_CACHE_KEY')
        ->toContain('Cache::put(self::STALE_CACHE_KEY, $catalog, now()->addHours(self::STALE_CACHE_HOURS));')
        ->toContain('$this->catalogNotice = ')
        ->toContain("->title('Farmadoc no respondió')");
});

it('alinea el control de cantidad justo debajo del encabezado «Cantidad»', function (): void {
    $page = farmadocTableSource('app/Filament/Operations/Pages/FarmadocMedicationOrder.php');
    $view = farmadocTableSource('resources/views/filament/operations/pages/farmadoc-medication-order.blade.php');

    $quantityColumn = substr($page, (int) strpos($page, "ViewColumn::make('order_quantity')"), 600);

    expect($quantityColumn)
        ->toContain('->alignCenter()')
        ->toContain("->width('11rem')")
        ->not->toContain('->alignEnd()');

    expect($view)
        ->toMatch('/\.fi-farmadoc-stepper-cell \{[^}]*justify-content: center;/')
        ->toMatch('/\.fi-farmadoc-stepper-cell \{[^}]*width: 100%;/')
        ->not->toContain('min-width: 8.5rem');
});

it('muestra el código de barras en la tabla, copiable, ordenable y buscable', function (): void {
    $page = farmadocTableSource('app/Filament/Operations/Pages/FarmadocMedicationOrder.php');

    expect($page)
        ->toContain("TextColumn::make('barcode')")
        ->toContain("->label('Código de barras')")
        ->toContain('->fontFamily(FontFamily::Mono)')
        ->toContain("->copyMessage('Código de barras copiado')")
        ->toContain("->searchPlaceholder('Buscar por nombre, código de barras, principio activo o SKU')");

    $barcodeColumn = strpos($page, "TextColumn::make('barcode')");
    expect($barcodeColumn)->toBeGreaterThan(strpos($page, "TextColumn::make('name')"))
        ->and($barcodeColumn)->toBeLessThan(strpos($page, "TextColumn::make('active_ingredient')"));

    $medications = [
        ['product_id' => 1, 'name' => 'Losartan', 'sku' => 'A', 'barcode' => '7591929232021'],
        ['product_id' => 2, 'name' => 'Acetaminofen', 'sku' => 'B', 'barcode' => '7590000000001'],
    ];

    $found = \App\Support\Farmadoc\FarmadocMedicationList::paginate($medications, '7591929232021', 1, 25);
    expect(collect($found->items())->pluck('product_id')->all())->toBe([1]);

    $sorted = \App\Support\Farmadoc\FarmadocMedicationList::paginate($medications, null, 1, 25, 'barcode', 'asc');
    expect(collect($sorted->items())->pluck('barcode')->all())->toBe(['7590000000001', '7591929232021']);
});
