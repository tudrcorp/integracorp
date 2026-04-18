<?php

namespace App\Filament\Administration\Resources\RrhhDepartamentos\Pages;

use App\Filament\Administration\Resources\RrhhDepartamentos\RrhhDepartamentoResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateRrhhDepartamento extends CreateRecord
{
    protected static string $resource = RrhhDepartamentoResource::class;

    protected static ?string $title = 'Nuevo Departamento';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(RrhhDepartamentoResource::getUrl('index'))
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ], merge: true),
        ];
    }
}
