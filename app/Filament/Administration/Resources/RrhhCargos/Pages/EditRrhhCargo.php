<?php

namespace App\Filament\Administration\Resources\RrhhCargos\Pages;

use App\Filament\Administration\Resources\RrhhCargos\RrhhCargoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRrhhCargo extends EditRecord
{
    protected static string $resource = RrhhCargoResource::class;

    protected static ?string $title = 'Editar Cargo';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(RrhhCargoResource::getUrl('index'))
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ], merge: true),
            DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->extraAttributes([
                    'class' => self::IOS_DANGER_BUTTON_CLASS,
                ], merge: true),
        ];
    }
}
