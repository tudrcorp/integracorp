<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Activities;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Projects\Resources\ProjectManagement\Activities\Pages\CreateActivity;
use App\Filament\Projects\Resources\ProjectManagement\Activities\Pages\EditActivity;
use App\Filament\Projects\Resources\ProjectManagement\Activities\Pages\ListActivities;
use App\Filament\Projects\Resources\ProjectManagement\Activities\Pages\ViewActivity;
use App\Filament\Projects\Resources\ProjectManagement\Activities\Schemas\ActivityForm;
use App\Filament\Projects\Resources\ProjectManagement\Activities\Schemas\ActivityInfolist;
use App\Filament\Projects\Resources\ProjectManagement\Activities\Tables\ActivitiesTable;
use App\Models\ProjectManagement\Activity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ActivityResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Activity::class;

    protected static ?string $navigationLabel = 'Actividades';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'GESTION DE PROYECTOS';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return ActivityForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ActivityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->with([
                'notesLogs' => fn ($query) => $query
                    ->with('author:id,name,email')
                    ->latest(),
                'documents' => fn ($query) => $query
                    ->with('uploader:id,name,email')
                    ->latest(),
            ])
            ->withCount(['notesLogs', 'documents']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'create' => CreateActivity::route('/create'),
            'view' => ViewActivity::route('/{record}'),
            'edit' => EditActivity::route('/{record}/edit'),
        ];
    }
}
