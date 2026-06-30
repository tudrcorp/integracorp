<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Groups;

use App\Filament\Projects\Resources\ProjectManagement\Groups\Pages\CreateGroup;
use App\Filament\Projects\Resources\ProjectManagement\Groups\Pages\EditGroup;
use App\Filament\Projects\Resources\ProjectManagement\Groups\Pages\ListGroups;
use App\Filament\Projects\Resources\ProjectManagement\Groups\Pages\ViewGroup;
use App\Filament\Projects\Resources\ProjectManagement\Groups\Schemas\GroupForm;
use App\Filament\Projects\Resources\ProjectManagement\Groups\Schemas\GroupInfolist;
use App\Filament\Projects\Resources\ProjectManagement\Groups\Tables\GroupsTable;
use App\Models\ProjectManagement\Group;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static ?string $navigationLabel = 'Equipos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'GESTION DE PROYECTOS';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return GroupForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return GroupInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGroups::route('/'),
            'create' => CreateGroup::route('/create'),
            'view' => ViewGroup::route('/{record}'),
            'edit' => EditGroup::route('/{record}/edit'),
        ];
    }
}
