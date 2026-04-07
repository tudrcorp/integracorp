<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use Filament\Resources\Pages\ListRecords;

it('define pestañas y contenedor personalizado para filtrar por estatus', function () {
    expect(is_subclass_of(ListSuppliers::class, ListRecords::class))->toBeTrue()
        ->and(method_exists(ListSuppliers::class, 'getTabs'))->toBeTrue()
        ->and(method_exists(ListSuppliers::class, 'getTabsContentComponent'))->toBeTrue();
});
