<?php

namespace App\Filament\Marketing\Resources\Agencies;

use App\Filament\Marketing\Resources\Agencies\Pages\CreateAgency;
use App\Filament\Marketing\Resources\Agencies\Pages\EditAgency;
use App\Filament\Marketing\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Marketing\Resources\Agencies\Pages\ViewAgency;
use App\Filament\Marketing\Resources\Agencies\Schemas\AgencyForm;
use App\Filament\Marketing\Resources\Agencies\Schemas\AgencyInfolist;
use App\Filament\Marketing\Resources\Agencies\Tables\AgenciesTable;
use App\Models\Agency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-c-building-library';

    protected static string | UnitEnum | null $navigationGroup = 'Estructura TDG';

    protected static ?string $navigationLabel = 'Agencias';

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
            //
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