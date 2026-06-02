<?php

namespace App\Filament\Operations\Resources\CorporateAllies\Pages;

use App\Filament\Operations\Resources\CorporateAllies\CorporateAllyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorporateAllies extends ListRecords
{
    protected static string $resource = CorporateAllyResource::class;

    protected static ?string $title = 'Aliados corporativos';

    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear aliado corporativo')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
        ];
    }
}
