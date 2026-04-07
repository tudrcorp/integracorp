<?php

namespace App\Filament\Operations\Resources\OperationInventories\Pages;

use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationInventories extends ListRecords
{
    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static string $resource = OperationInventoryResource::class;

    protected static ?string $title = 'Inventario De Productos/Medicamentos';

    protected function getHeaderActions(): array
    {
        return [
            // Action::make('back')
            //     ->label('Volver')
            //     ->icon('heroicon-o-arrow-left')
            //     ->color('gray')
            //     ->url('/operations'),
            CreateAction::make()
                ->label('Nuevo Producto/Medicamento')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),

        ];
    }
}
