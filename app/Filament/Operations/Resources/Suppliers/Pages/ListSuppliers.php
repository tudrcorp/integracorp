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

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Proveedor')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
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
