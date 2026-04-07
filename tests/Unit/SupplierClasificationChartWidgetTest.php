<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Widgets\SupplierClasificationChart;

it('define el widget de gráfico de proveedores por clasificación', function () {
    expect(class_exists(SupplierClasificationChart::class))->toBeTrue()
        ->and(is_subclass_of(SupplierClasificationChart::class, \Filament\Widgets\ChartWidget::class))->toBeTrue();
});
