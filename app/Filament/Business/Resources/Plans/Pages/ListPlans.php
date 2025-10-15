<?php

namespace App\Filament\Business\Resources\Plans\Pages;

use App\Filament\Business\Resources\Plans\PlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlans extends ListRecords
{
    protected static string $resource = PlanResource::class;

    protected static ?string $title = 'Listado de Planes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear plan')
                ->icon('heroicon-s-plus')
                ->color('primary'),
        ];
    }
}