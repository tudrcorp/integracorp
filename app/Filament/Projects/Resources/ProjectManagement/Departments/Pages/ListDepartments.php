<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Departments\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Departments\DepartmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
