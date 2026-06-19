<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTelemedicinePatients extends ListRecords
{
    /**
     * Misma apariencia que el botón "Crear Ticket" (menu-user): ticket-btn-ios en theme.css + píldora rounded-full.
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /** Misma forma iOS que TICKET_BUTTON_CLASS pero gris (theme.css .ticket-btn-ios-gray) */
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Lista de Pacientes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Nuevo Paciente')
                ->icon('heroicon-s-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ])
                ->visible(fn (): bool => OperationsSupplierScope::currentSupplierId() === null),
        ];
    }
}
