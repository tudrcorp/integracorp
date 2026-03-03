<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Filament\Operations\Resources\Suppliers\Widgets\StatsOverviewGeneralSupplier;
use App\Filament\Operations\Resources\Suppliers\Widgets\StatsOverviewPreferencialSupplier;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Lista de Proveedores';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Proveedor')
                ->icon('heroicon-s-plus')
                ->color('primary'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewGeneralSupplier::class,
            StatsOverviewPreferencialSupplier::class,
        ];
    }
}
