<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Sprints;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\Pages\CreateSprint;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\Pages\EditSprint;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\Pages\ListSprints;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\Pages\ViewSprint;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\RelationManagers\CeremoniesRelationManager;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\Schemas\SprintForm;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\Schemas\SprintInfolist;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\Tables\SprintsTable;
use App\Models\ProjectManagement\Sprint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SprintResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Sprint::class;

    protected static ?string $navigationLabel = 'Sprints';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static string|UnitEnum|null $navigationGroup = 'GESTION DE PROYECTOS';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return SprintForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SprintInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SprintsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CeremoniesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSprints::route('/'),
            'create' => CreateSprint::route('/create'),
            'view' => ViewSprint::route('/{record}'),
            'edit' => EditSprint::route('/{record}/edit'),
        ];
    }
}
