<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Widgets\StatsOverviewGeneralSupplier;

it('define el widget de proveedores generales por estatus de sistema', function () {
    expect(class_exists(StatsOverviewGeneralSupplier::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverviewGeneralSupplier::class, \Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
});
