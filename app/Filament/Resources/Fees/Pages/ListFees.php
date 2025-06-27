<?php

namespace App\Filament\Resources\Fees\Pages;

use App\Filament\Resources\Fees\FeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFees extends ListRecords
{
    protected static string $resource = FeeResource::class;

    protected static ?string $title = 'GESTION DE TARIFAS POR RANGO DE EDADES';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('CREAR')
                ->icon('heroicon-m-book-open')
        ];
    }
}