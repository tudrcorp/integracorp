<?php

namespace App\Filament\Business\Resources\AccountManagers\Pages;

use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;
use App\Filament\Business\Resources\AccountManagers\Widgets\StatsOverviewTotalAccountManager;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccountManagers extends ListRecords
{
    protected static string $resource = AccountManagerResource::class;

    protected static ?string $title = 'Cuentas de Account Managers';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderWidgets(): array
    {

        return [
            StatsOverviewTotalAccountManager::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Account Manager')
                ->icon('heroicon-s-user-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}
