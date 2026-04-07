<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Widgets\SupplierStatsOverviewFirts;

it('define el widget de resumen de convenios de proveedores', function () {
    expect(class_exists(SupplierStatsOverviewFirts::class))->toBeTrue()
        ->and(is_subclass_of(SupplierStatsOverviewFirts::class, \Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
});
