<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Widgets\SupplierForState;

it('define el widget de gráfico de proveedores por estado', function () {
    expect(class_exists(SupplierForState::class))->toBeTrue()
        ->and(is_subclass_of(SupplierForState::class, \Filament\Widgets\ChartWidget::class))->toBeTrue();
});

it('asigna colores de dataset según el estatus en sistema', function (string $label, string $expectedFill) {
    $widget = new SupplierForState;
    $method = new ReflectionMethod(SupplierForState::class, 'glassColorsForSistemaStatus');
    $method->setAccessible(true);
    /** @var array{fill: string, stroke: string} $colors */
    $colors = $method->invoke($widget, $label);

    expect($colors['fill'])->toBe($expectedFill);
})->with([
    'afiliado' => ['AFILIADO', 'rgba(70, 146, 60, 0.82)'],
    'activo afiliado' => ['ACTIVO AFILIADO', 'rgba(39, 98, 33, 0.82)'],
    'en proceso' => ['EN PROCESO', 'rgba(238, 159, 39, 0.82)'],
    'activo en proceso' => ['ACTIVO EN PROCESO', 'rgba(220, 102, 1, 0.82)'],
    'sin respuesta' => ['SIN RESPUESTA', 'rgba(205, 0, 0, 0.82)'],
    'no desea convenio' => ['NO DESEA CONVENIO', 'rgba(179, 0, 0, 0.82)'],
    'suspendido proveedor' => ['CONVENIO SUSPENDIDO POR EL PROVEEDOR', 'rgba(154, 0, 0, 0.85)'],
    'suspendido tdec' => ['CONVENIO SUSPENDIDO POR TDEC', 'rgba(130, 0, 0, 0.9)'],
    'conveio tdec' => ['Conveio suspendido por TDEC', 'rgba(130, 0, 0, 0.9)'],
    'sin estatus' => ['Sin estatus en sistema', 'rgba(142, 142, 147, 0.42)'],
]);

it('usa gris neutro para un estatus no reconocido', function () {
    $widget = new SupplierForState;
    $method = new ReflectionMethod(SupplierForState::class, 'glassColorsForSistemaStatus');
    $method->setAccessible(true);
    /** @var array{fill: string, stroke: string} $colors */
    $colors = $method->invoke($widget, 'Estatus legacy desconocido');

    expect($colors['fill'])->toBe('rgba(120, 120, 128, 0.48)');
});
