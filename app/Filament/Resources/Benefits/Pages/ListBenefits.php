<?php

namespace App\Filament\Resources\Benefits\Pages;

use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\Benefits\BenefitResource;

class ListBenefits extends ListRecords
{
    protected static string $resource = BenefitResource::class;

    protected static ?string $title = 'GESTIÓN DE BENEFICIOS';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon(Heroicon::Share)
        ];
    }
}