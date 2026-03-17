<?php

namespace App\Filament\Business\Resources\Benefits\Pages;

use App\Filament\Business\Resources\Benefits\BenefitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBenefits extends ListRecords
{
    protected static string $resource = BenefitResource::class;

    protected static ?string $title = 'Crear Beneficios';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Contacto')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}