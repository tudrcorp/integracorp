<?php

namespace App\Filament\Operations\Resources\CorporateAllies\Pages;

use App\Filament\Operations\Resources\CorporateAllies\CorporateAllyResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCorporateAlly extends ViewRecord
{
    protected static string $resource = CorporateAllyResource::class;

    protected static ?string $title = 'Ficha del Aliado Corporativo';

    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(CorporateAllyResource::getUrl('index'))
                ->extraAttributes(['class' => self::TICKET_BUTTON_GRAY_CLASS]),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->extraAttributes(['class' => self::PRIMARY_BUTTON_CLASS]),
        ];
    }
}
