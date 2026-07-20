<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Epics;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Projects\Resources\ProjectManagement\Epics\Pages\CreateEpic;
use App\Filament\Projects\Resources\ProjectManagement\Epics\Pages\EditEpic;
use App\Filament\Projects\Resources\ProjectManagement\Epics\Pages\ListEpics;
use App\Filament\Projects\Resources\ProjectManagement\Epics\Pages\ViewEpic;
use App\Filament\Projects\Resources\ProjectManagement\Epics\Schemas\EpicForm;
use App\Filament\Projects\Resources\ProjectManagement\Epics\Schemas\EpicInfolist;
use App\Filament\Projects\Resources\ProjectManagement\Epics\Tables\EpicsTable;
use App\Models\ProjectManagement\Epic;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class EpicResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Epic::class;

    protected static ?string $navigationLabel = 'Épicas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bookmark-square';

    protected static string|UnitEnum|null $navigationGroup = 'GESTION DE PROYECTOS';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return EpicForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EpicInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EpicsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEpics::route('/'),
            'create' => CreateEpic::route('/create'),
            'view' => ViewEpic::route('/{record}'),
            'edit' => EditEpic::route('/{record}/edit'),
        ];
    }
}
