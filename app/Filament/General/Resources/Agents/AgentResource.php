<?php

namespace App\Filament\General\Resources\Agents;

use App\Filament\General\Resources\Agents\Pages\CreateAgent;
use App\Filament\General\Resources\Agents\Pages\EditAgent;
use App\Filament\General\Resources\Agents\Pages\ListAgents;
use App\Filament\General\Resources\Agents\Pages\ViewAgent;
use App\Filament\General\Resources\Agents\Schemas\AgentForm;
use App\Filament\General\Resources\Agents\Schemas\AgentInfolist;
use App\Filament\General\Resources\Agents\Tables\AgentsTable;
use App\Models\Agent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static string | UnitEnum | null $navigationGroup = 'Organización';

    protected static ?string $navigationLabel = 'Agentes';

    public static function form(Schema $schema): Schema
    {
        return AgentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AgentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgentsTable::configure($table);
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
            'index' => ListAgents::route('/'),
            'create' => CreateAgent::route('/create'),
            'view' => ViewAgent::route('/{record}'),
            'edit' => EditAgent::route('/{record}/edit'),
        ];
    }
}