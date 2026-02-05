<?php

namespace App\Filament\Business\Resources\Agents;

use App\Filament\Business\Resources\Agents\Pages\CreateAgent;
use App\Filament\Business\Resources\Agents\Pages\EditAgent;
use App\Filament\Business\Resources\Agents\Pages\ListAgents;
use App\Filament\Business\Resources\Agents\Pages\ViewAgent;
use App\Filament\Business\Resources\Agents\Schemas\AgentForm;
use App\Filament\Business\Resources\Agents\Schemas\AgentInfolist;
use App\Filament\Business\Resources\Agents\Tables\AgentsTable;
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

    protected static ?string $navigationLabel = 'Agentes De Corretaje';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string | UnitEnum | null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?int $navigationSort = 2;

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