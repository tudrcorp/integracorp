<?php

namespace App\Filament\Business\Resources\TravelAgents;

use App\Filament\Business\Resources\TravelAgents\Pages\CreateTravelAgent;
use App\Filament\Business\Resources\TravelAgents\Pages\EditTravelAgent;
use App\Filament\Business\Resources\TravelAgents\Pages\ListTravelAgents;
use App\Filament\Business\Resources\TravelAgents\Schemas\TravelAgentForm;
use App\Filament\Business\Resources\TravelAgents\Tables\TravelAgentsTable;
use App\Models\TravelAgent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TravelAgentResource extends Resource
{
    protected static ?string $model = TravelAgent::class;

    protected static ?string $navigationLabel = 'Agentes De Viaje';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string | UnitEnum | null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    public static function form(Schema $schema): Schema
    {
        return TravelAgentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TravelAgentsTable::configure($table);
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
            'index' => ListTravelAgents::route('/'),
            'create' => CreateTravelAgent::route('/create'),
            'edit' => EditTravelAgent::route('/{record}/edit'),
        ];
    }
}
