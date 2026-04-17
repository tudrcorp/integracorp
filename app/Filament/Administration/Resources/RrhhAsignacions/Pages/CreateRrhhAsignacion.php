<?php

namespace App\Filament\Administration\Resources\RrhhAsignacions\Pages;

use App\Filament\Administration\Resources\RrhhAsignacions\RrhhAsignacionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateRrhhAsignacion extends CreateRecord
{
    protected static string $resource = RrhhAsignacionResource::class;

    protected static ?string $title = 'Nueva Asignación';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

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
        ];
    }
}
