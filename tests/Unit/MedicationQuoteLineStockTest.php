<?php

declare(strict_types=1);

use App\Support\Operations\MedicationQuoteLineStock;

it('no muestra existencia en laboratorios, estudios ni especialistas', function (): void {
    expect(MedicationQuoteLineStock::hint('Laboratorio', 'HEMOGRAMA'))->toBeNull()
        ->and(MedicationQuoteLineStock::hint('Estudio', 'RX'))->toBeNull()
        ->and(MedicationQuoteLineStock::hint('Especialista', 'CARDIOLOGIA'))->toBeNull()
        ->and(MedicationQuoteLineStock::hint('Servicio', 'CONSULTA'))->toBeNull();
});

it('no pide reposición si la lista no tiene medicamentos', function (): void {
    expect(MedicationQuoteLineStock::listNeedsSupplierOrder(null))->toBeFalse()
        ->and(MedicationQuoteLineStock::listNeedsSupplierOrder([]))->toBeFalse()
        ->and(MedicationQuoteLineStock::listNeedsSupplierOrder([
            ['category' => 'Laboratorio', 'label' => 'HEMOGRAMA'],
            ['category' => 'Estudio', 'label' => 'RX'],
            ['category' => 'Especialista', 'label' => 'CARDIOLOGIA'],
        ]))->toBeFalse();
});

it('pide reposición cuando el medicamento no está en el inventario', function (): void {
    expect(MedicationQuoteLineStock::lineNeedsSupplierOrder(0, []))->toBeTrue()
        ->and(MedicationQuoteLineStock::lineNeedsSupplierOrder(1, [
            ['existence' => 6, 'minimum' => 5],
        ]))->toBeFalse();
});

it('marca reposición cuando la existencia no supera el mínimo registrado', function (): void {
    expect(MedicationQuoteLineStock::medicationNeedsReorder([]))->toBeFalse()
        ->and(MedicationQuoteLineStock::medicationNeedsReorder([
            ['existence' => 3, 'minimum' => 5],
        ]))->toBeTrue()
        ->and(MedicationQuoteLineStock::medicationNeedsReorder([
            ['existence' => 5, 'minimum' => 5],
        ]))->toBeTrue()
        ->and(MedicationQuoteLineStock::medicationNeedsReorder([
            ['existence' => 6, 'minimum' => 5],
        ]))->toBeFalse()
        ->and(MedicationQuoteLineStock::medicationNeedsReorder([
            ['existence' => 2, 'minimum' => 5],
            ['existence' => 4, 'minimum' => 5],
        ]))->toBeTrue()
        ->and(MedicationQuoteLineStock::medicationNeedsReorder([
            ['existence' => 2, 'minimum' => 5],
            ['existence' => 20, 'minimum' => 5],
        ]))->toBeFalse()
        ->and(MedicationQuoteLineStock::medicationNeedsReorder([
            ['existence' => 0, 'minimum' => 0],
        ]))->toBeTrue();
});

it('escribe la existencia del medicamento o avisa que no está en el inventario', function (): void {
    expect(MedicationQuoteLineStock::text(['matches' => 0, 'units' => 0]))
        ->toBe('Sin existencia registrada en el inventario.')
        ->and(MedicationQuoteLineStock::text(['matches' => 2, 'units' => 0]))
        ->toBe('Existencia: 0')
        ->and(MedicationQuoteLineStock::text(['matches' => 1, 'units' => 12]))
        ->toBe('Existencia: 12')
        ->and(MedicationQuoteLineStock::text(['matches' => 3, 'units' => 1500]))
        ->toBe('Existencia: 1.500');
});
