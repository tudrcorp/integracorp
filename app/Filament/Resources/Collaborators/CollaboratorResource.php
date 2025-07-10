<?php

namespace App\Filament\Resources\Collaborators;

use App\Filament\Resources\Collaborators\Pages\CreateCollaborator;
use App\Filament\Resources\Collaborators\Pages\EditCollaborator;
use App\Filament\Resources\Collaborators\Pages\ListCollaborators;
use App\Filament\Resources\Collaborators\Pages\ViewCollaborator;
use App\Filament\Resources\Collaborators\Schemas\CollaboratorForm;
use App\Filament\Resources\Collaborators\Schemas\CollaboratorInfolist;
use App\Filament\Resources\Collaborators\Tables\CollaboratorsTable;
use App\Models\Collaborator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CollaboratorResource extends Resource
{
    protected static ?string $model = Collaborator::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserPlus;

    protected static string | UnitEnum | null $navigationGroup = 'TDEC';

    protected static ?string $navigationLabel = 'Colaboradores';

    public static function form(Schema $schema): Schema
    {
        return CollaboratorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CollaboratorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CollaboratorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCollaborators::route('/'),
            'create' => CreateCollaborator::route('/create'),
            'view' => ViewCollaborator::route('/{record}'),
            'edit' => EditCollaborator::route('/{record}/edit'),
        ];
    }
}