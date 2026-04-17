<?php

namespace App\Filament\Administration\Resources\RrhhDeduccions\Pages;

use App\Filament\Administration\Resources\RrhhDeduccions\RrhhDeduccionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateRrhhDeduccion extends CreateRecord
{
    protected static string $resource = RrhhDeduccionResource::class;

    protected static ?string $title = 'Nueva Deducción';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(RrhhDeduccionResource::getUrl('index'))
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ], merge: true),
        ];
    }
}
