<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Departments;

use App\Filament\Projects\Resources\ProjectManagement\Departments\Pages\CreateDepartment;
use App\Filament\Projects\Resources\ProjectManagement\Departments\Pages\EditDepartment;
use App\Filament\Projects\Resources\ProjectManagement\Departments\Pages\ListDepartments;
use App\Filament\Projects\Resources\ProjectManagement\Departments\Pages\ViewDepartment;
use App\Filament\Projects\Resources\ProjectManagement\Departments\Schemas\DepartmentForm;
use App\Filament\Projects\Resources\ProjectManagement\Departments\Schemas\DepartmentInfolist;
use App\Filament\Projects\Resources\ProjectManagement\Departments\Tables\DepartmentsTable;
use App\Models\ProjectManagement\Department;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $navigationLabel = 'Departamentos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'GESTION DE PROYECTOS';

    public static function form(Schema $schema): Schema
    {
        return DepartmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DepartmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartments::route('/'),
            'create' => CreateDepartment::route('/create'),
            'view' => ViewDepartment::route('/{record}'),
            'edit' => EditDepartment::route('/{record}/edit'),
        ];
    }
}
