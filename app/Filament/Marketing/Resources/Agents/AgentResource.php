<?php

namespace App\Filament\Marketing\Resources\Agents;

use App\Filament\Marketing\Resources\Agents\Pages\CreateAgent;
use App\Filament\Marketing\Resources\Agents\Pages\EditAgent;
use App\Filament\Marketing\Resources\Agents\Pages\ListAgents;
use App\Filament\Marketing\Resources\Agents\Pages\ViewAgent;
use App\Filament\Marketing\Resources\Agents\Schemas\AgentForm;
use App\Filament\Marketing\Resources\Agents\Schemas\AgentInfolist;
use App\Filament\Marketing\Resources\Agents\Tables\AgentsTable;
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

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-c-academic-cap';

    protected static string | UnitEnum | null $navigationGroup = 'Estructura TDG';

    protected static ?string $navigationLabel = 'Agentes De Corretaje';

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