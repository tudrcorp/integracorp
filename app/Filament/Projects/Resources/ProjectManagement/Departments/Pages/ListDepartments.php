<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Departments\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Departments\DepartmentResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

    protected static ?string $title = 'Departamentos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo departamento')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
        ];
    }
}
