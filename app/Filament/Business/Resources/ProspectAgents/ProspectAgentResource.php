<?php

namespace App\Filament\Business\Resources\ProspectAgents;

use App\Filament\Business\Resources\ProspectAgents\Pages\CreateProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\Pages\EditProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\Pages\ListProspectAgents;
use App\Filament\Business\Resources\ProspectAgents\Pages\ViewProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\RelationManagers\ProspectAgentTasksRelationManager;
use App\Filament\Business\Resources\ProspectAgents\Schemas\ProspectAgentForm;
use App\Filament\Business\Resources\ProspectAgents\Schemas\ProspectAgentInfolist;
use App\Filament\Business\Resources\ProspectAgents\Tables\ProspectAgentsTable;
use App\Models\ProspectAgent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ProspectAgentResource extends Resource
{
    protected static ?string $model = ProspectAgent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-user-plus';

    protected static string | UnitEnum | null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?string $navigationLabel = 'Agente Prospecto';

    public static function form(Schema $schema): Schema
    {
        return ProspectAgentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProspectAgentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProspectAgentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProspectAgentTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProspectAgents::route('/'),
            'create' => CreateProspectAgent::route('/create'),
            'view' => ViewProspectAgent::route('/{record}'),
            'edit' => EditProspectAgent::route('/{record}/edit'),
        ];
    }
}
