<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Widgets\StatsOverviewPreferencialSupplier;

it('define el widget de proveedores preferenciales por estatus de sistema', function () {
    expect(class_exists(StatsOverviewPreferencialSupplier::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverviewPreferencialSupplier::class, \Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
});
