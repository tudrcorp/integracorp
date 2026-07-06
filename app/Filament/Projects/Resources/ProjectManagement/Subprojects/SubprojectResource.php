<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Subprojects;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Projects\Resources\ProjectManagement\Subprojects\Pages\CreateSubproject;
use App\Filament\Projects\Resources\ProjectManagement\Subprojects\Pages\EditSubproject;
use App\Filament\Projects\Resources\ProjectManagement\Subprojects\Pages\ListSubprojects;
use App\Filament\Projects\Resources\ProjectManagement\Subprojects\Pages\ViewSubproject;
use App\Filament\Projects\Resources\ProjectManagement\Subprojects\Schemas\SubprojectForm;
use App\Filament\Projects\Resources\ProjectManagement\Subprojects\Schemas\SubprojectInfolist;
use App\Filament\Projects\Resources\ProjectManagement\Subprojects\Tables\SubprojectsTable;
use App\Models\ProjectManagement\Subproject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SubprojectResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Subproject::class;

    protected static ?string $navigationLabel = 'Subproyectos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|UnitEnum|null $navigationGroup = 'GESTION DE PROYECTOS';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return SubprojectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SubprojectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubprojectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubprojects::route('/'),
            'create' => CreateSubproject::route('/create'),
            'view' => ViewSubproject::route('/{record}'),
            'edit' => EditSubproject::route('/{record}/edit'),
        ];
    }
}
