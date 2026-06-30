<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Projects;

use App\Filament\Projects\Resources\ProjectManagement\Projects\Pages\CreateProject;
use App\Filament\Projects\Resources\ProjectManagement\Projects\Pages\EditProject;
use App\Filament\Projects\Resources\ProjectManagement\Projects\Pages\ListProjects;
use App\Filament\Projects\Resources\ProjectManagement\Projects\Pages\ViewProject;
use App\Filament\Projects\Resources\ProjectManagement\Projects\Schemas\ProjectForm;
use App\Filament\Projects\Resources\ProjectManagement\Projects\Schemas\ProjectInfolist;
use App\Filament\Projects\Resources\ProjectManagement\Projects\Tables\ProjectsTable;
use App\Models\ProjectManagement\Project;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationLabel = 'Proyectos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string|UnitEnum|null $navigationGroup = 'GESTION DE PROYECTOS';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
