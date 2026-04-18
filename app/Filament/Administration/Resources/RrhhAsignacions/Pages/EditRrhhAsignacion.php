<?php

namespace App\Filament\Administration\Resources\RrhhAsignacions\Pages;

use App\Filament\Administration\Resources\RrhhAsignacions\RrhhAsignacionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRrhhAsignacion extends EditRecord
{
    protected static string $resource = RrhhAsignacionResource::class;

    protected static ?string $title = 'Editar Asignación';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(RrhhAsignacionResource::getUrl('index'))
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
