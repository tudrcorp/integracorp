<?php

declare(strict_types=1);

use App\Services\TelemedicineMedicationInventoryDeductor;
use App\Services\TelemedicineSupplyConsumptionRecorder;
use App\Support\Telemedicine\TelemedicineSupplyInventoryOptions;

uses(Tests\TestCase::class);

it('normaliza las filas del repetidor y agrega cantidades del mismo insumo', function (): void {
    $rows = TelemedicineSupplyInventoryOptions::normalizeRows([
        ['operation_inventory_id' => 5, 'quantity' => 2],
        ['operation_inventory_id' => 5, 'quantity' => 3],
        ['operation_inventory_id' => 8, 'quantity' => 1],
    ]);

    expect($rows)->toBe([
        ['operation_inventory_id' => 5, 'quantity' => 5],
        ['operation_inventory_id' => 8, 'quantity' => 1],
    ]);
});

it('descarta filas incompletas, cantidades no positivas y basura', function (): void {
    expect(TelemedicineSupplyInventoryOptions::normalizeRows([
        ['operation_inventory_id' => 0, 'quantity' => 9],
        ['operation_inventory_id' => 7, 'quantity' => 0],
        ['operation_inventory_id' => 7, 'quantity' => -3],
        ['quantity' => 4],
        'basura',
        null,
    ]))->toBe([]);

    expect(TelemedicineSupplyInventoryOptions::normalizeRows('no es un arreglo'))->toBe([])
        ->and(TelemedicineSupplyInventoryOptions::normalizeRows(null))->toBe([]);
});

it('filtra el inventario por la categoría de producto Insumos', function (): void {
    $sql = mb_strtoupper(TelemedicineSupplyInventoryOptions::supplyInventoriesQuery()->toSql());

    expect($sql)
        ->toContain('OPERATION_INVENTORIES')
        ->toContain('IS_ACTIVE')
        ->toContain('EXISTS');

    expect(TelemedicineSupplyInventoryOptions::CATEGORY_INSUMO_LIKE)->toBe('INSUMO%');

    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineSupplyInventoryOptions.php');

    expect($source)
        ->toContain("whereHas('product.category'")
        ->toContain('UPPER(name) LIKE ?');
});

it('acota los insumos al almacén y a existencia positiva cuando se descuenta', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineSupplyInventoryOptions.php');

    expect($source)
        ->toContain("->where('existence', '>', 0)")
        ->toContain('TelemedicineMedicationInventoryOptions::constrainInventoryToWarehouse')
        ->toContain('TelemedicineMedicationInventoryOptions::shouldDeductInventory')
        ->toContain('uniqueSupplyCatalogOptions');
});

it('etiqueta las opciones con unidad y existencia solo cuando aplica descuento', function (): void {
    $inventory = new App\Models\OperationInventory([
        'name' => 'GUANTES DE NITRILO',
        'unit' => 'CAJA',
        'existence' => 12,
    ]);

    expect(TelemedicineSupplyInventoryOptions::optionLabel($inventory, withExistence: true))
        ->toBe('GUANTES DE NITRILO (CAJA) · Disponible: 12')
        ->and(TelemedicineSupplyInventoryOptions::optionLabel($inventory, withExistence: false))
        ->toBe('GUANTES DE NITRILO (CAJA)');

    $sinUnidad = new App\Models\OperationInventory(['name' => 'GASA', 'existence' => 3]);

    expect(TelemedicineSupplyInventoryOptions::optionLabel($sinUnidad, withExistence: false))->toBe('GASA');
});

it('el deductor acepta tipo de movimiento y sustantivo sin romper el uso de medicamentos', function (): void {
    $method = new ReflectionMethod(TelemedicineMedicationInventoryDeductor::class, 'deductIfApplicable');
    $parameters = collect($method->getParameters())->keyBy(fn (ReflectionParameter $p): string => $p->getName());

    expect($parameters->keys()->all())->toContain('movementType', 'itemLabel')
        ->and($parameters->get('movementType')->isOptional())->toBeTrue()
        ->and($parameters->get('itemLabel')->getDefaultValue())->toBe('medicamento')
        ->and($parameters->get('quantity')->getPosition())->toBeLessThan($parameters->get('movementType')->getPosition());

    expect(TelemedicineMedicationInventoryDeductor::MOVEMENT_TYPE)->toBe('SALIDA TELEMEDICINA')
        ->and(TelemedicineMedicationInventoryDeductor::MOVEMENT_TYPE_SUPPLY)->toBe('SALIDA INSUMOS TELEMEDICINA');
});

it('registra el consumo de forma idempotente y respeta la regla de descuento', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/TelemedicineSupplyConsumptionRecorder.php');

    expect($source)
        ->toContain('alreadyRecordedQuantities')
        ->toContain("\$quantity = \$row['quantity'] - (int) (\$alreadyRecorded[\$inventoryId] ?? 0);")
        ->toContain('TelemedicineMedicationInventoryOptions::shouldDeductInventory')
        ->toContain('recordWithoutDeduction')
        ->toContain('DB::transaction')
        ->toContain('TelemedicineMedicationInventoryDeductor::MOVEMENT_TYPE_SUPPLY');

    expect(TelemedicineSupplyConsumptionRecorder::STATUS_DECLARED)->toBe('DECLARADO')
        ->and(TelemedicineSupplyConsumptionRecorder::STATUS_DISPATCHED)->toBe('DESPACHADO');
});

it('expone el fieldset de insumos en la consulta inicial y en el seguimiento', function (): void {
    $root = dirname(__DIR__, 2);
    $form = file_get_contents($root.'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect(substr_count($form, 'self::medicalSuppliesFieldset($case)'))->toBe(2);

    expect($form)
        ->toContain("Fieldset::make('Insumos médicos consumidos')")
        ->toContain("Repeater::make('medical_supplies')")
        ->toContain("Select::make('operation_inventory_id')")
        ->toContain("TextInput::make('quantity')")
        ->toContain('->distinct()')
        ->toContain('TelemedicineSupplyInventoryOptions::searchOptionsForCase')
        ->toContain('medical_supplies_empty_notice');

    // El fieldset va justo debajo del bloque de Observaciones en ambos pasos.
    preg_match_all('/Textarea::make\(\'observations\'\).*?self::medicalSuppliesFieldset\(\$case\)/s', $form, $matches);

    expect($matches[0])->toHaveCount(2);
});

it('conecta el registro de insumos al guardar la consulta en crear y editar', function (): void {
    $root = dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/';

    foreach (['CreateTelemedicineConsultationPatient.php', 'EditTelemedicineConsultationPatient.php'] as $page) {
        $contents = file_get_contents($root.$page);

        expect($contents)
            ->toContain('use App\Services\TelemedicineSupplyConsumptionRecorder;')
            ->toContain('app(TelemedicineSupplyConsumptionRecorder::class)')
            ->toContain('->recordAndNotify(')
            ->toContain("\$this->data['medical_supplies'] ?? []");
    }
});
