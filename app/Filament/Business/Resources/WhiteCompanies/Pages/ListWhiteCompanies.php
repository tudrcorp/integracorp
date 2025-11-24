<?php

namespace App\Filament\Business\Resources\WhiteCompanies\Pages;

use App\Filament\Business\Resources\WhiteCompanies\WhiteCompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhiteCompanies extends ListRecords
{
    protected static string $resource = WhiteCompanyResource::class;

    protected static ?string $title = 'Empresas (White-Label)';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Empresa')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
}