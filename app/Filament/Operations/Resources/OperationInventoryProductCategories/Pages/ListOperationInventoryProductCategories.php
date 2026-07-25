<?php

namespace App\Filament\Operations\Resources\OperationInventoryProductCategories\Pages;

use App\Filament\Operations\Resources\OperationInventoryProductCategories\OperationInventoryProductCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationInventoryProductCategories extends ListRecords
{
    protected static string $resource = OperationInventoryProductCategoryResource::class;

    protected static ?string $title = 'Categorías';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Categoría')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}
