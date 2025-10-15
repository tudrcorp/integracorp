<?php

namespace App\Filament\Business\Resources\Fees\Pages;

use App\Filament\Business\Resources\Fees\FeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFees extends ListRecords
{
    protected static string $resource = FeeResource::class;

    protected static ?string $title = 'Listado de Tarifas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Tarifa')
                ->icon('heroicon-m-book-open')
                ->color('primary'),    
        ];
    }
}