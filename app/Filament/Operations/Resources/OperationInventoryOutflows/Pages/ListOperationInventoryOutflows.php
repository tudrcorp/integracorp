<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows\Pages;

use App\Filament\Operations\Resources\OperationInventoryOutflows\OperationInventoryOutflowResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOperationInventoryOutflows extends ListRecords
{
    /**
     * Mismo estilo iOS gris que cancelar modal (theme.css .ticket-btn-ios-gray).
     */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static string $resource = OperationInventoryOutflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
            Action::make('back')
                ->label('Volver')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url('/operations/operation-inventories')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ]),
        ];
    }
}
