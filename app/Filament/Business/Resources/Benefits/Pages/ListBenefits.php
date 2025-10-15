<?php

namespace App\Filament\Business\Resources\Benefits\Pages;

use App\Filament\Business\Resources\Benefits\BenefitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBenefits extends ListRecords
{
    protected static string $resource = BenefitResource::class;

    protected static ?string $title = 'Beneficios';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Beneficio')
                ->icon('heroicon-s-plus')
                ->color('primary'),
        ];
    }
}