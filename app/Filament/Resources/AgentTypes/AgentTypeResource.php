<?php

namespace App\Filament\Resources\AgentTypes;

use App\Filament\Resources\AgentTypes\Pages\CreateAgentType;
use App\Filament\Resources\AgentTypes\Pages\EditAgentType;
use App\Filament\Resources\AgentTypes\Pages\ListAgentTypes;
use App\Filament\Resources\AgentTypes\Pages\ViewAgentType;
use App\Filament\Resources\AgentTypes\Schemas\AgentTypeForm;
use App\Filament\Resources\AgentTypes\Schemas\AgentTypeInfolist;
use App\Filament\Resources\AgentTypes\Tables\AgentTypesTable;
use App\Models\AgentType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AgentTypeResource extends Resource
{
    protected static ?string $model = AgentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;

    protected static string | UnitEnum | null $navigationGroup = 'TDEC';

    protected static ?string $navigationLabel = 'Tipos de Agente';

    public static function form(Schema $schema): Schema
    {
        return AgentTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AgentTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgentTypesTable::configure($table);
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
            'index' => ListAgentTypes::route('/'),
            'create' => CreateAgentType::route('/create'),
            'view' => ViewAgentType::route('/{record}'),
            'edit' => EditAgentType::route('/{record}/edit'),
        ];
    }
}