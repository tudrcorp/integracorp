<?php

namespace App\Filament\Administration\Resources\RrhhDepartamentos\Pages;

use App\Filament\Administration\Resources\RrhhDepartamentos\RrhhDepartamentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRrhhDepartamentos extends ListRecords
{
    protected static string $resource = RrhhDepartamentoResource::class;

    protected static ?string $title = 'Gestión de Departamentos';

    private const IOS_PRIMARY_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Agregar departamento')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->extraAttributes([
                    'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                ]),
        ];
    }
}
