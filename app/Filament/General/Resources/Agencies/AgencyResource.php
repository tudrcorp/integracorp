<?php

namespace App\Filament\General\Resources\Agencies;

use UnitEnum;
use BackedEnum;
use App\Models\Agency;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\General\Resources\Agencies\Pages\EditAgency;
use App\Filament\General\Resources\Agencies\Pages\ViewAgency;
use App\Filament\General\Resources\Agencies\Pages\CreateAgency;
use App\Filament\General\Resources\Agencies\Pages\ListAgencies;
use App\Filament\General\Resources\Agencies\Schemas\AgencyForm;
use App\Filament\General\Resources\Agencies\Tables\AgenciesTable;
use App\Filament\General\Resources\Agencies\Schemas\AgencyInfolist;
use App\Filament\General\Resources\Agencies\RelationManagers\DocumentsRelationManager;

class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    protected static string | UnitEnum | null $navigationGroup = 'Organización';

    protected static ?string $navigationLabel = 'Agencias';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return AgencyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AgencyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgenciesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgencies::route('/'),
            'create' => CreateAgency::route('/create'),
            'view' => ViewAgency::route('/{record}'),
            'edit' => EditAgency::route('/{record}/edit'),
        ];
    }
}