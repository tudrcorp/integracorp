<?php

namespace App\Filament\Resources\Limits\Pages;

use App\Filament\Resources\Limits\LimitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLimits extends ListRecords
{
    protected static string $resource = LimitResource::class;

    protected static ?string $title = 'Gestión de Limites para Beneficios';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon('heroicon-c-adjustments-horizontal')
        ];
    }
}