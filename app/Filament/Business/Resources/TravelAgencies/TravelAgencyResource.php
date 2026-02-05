<?php

namespace App\Filament\Business\Resources\TravelAgencies;

use App\Filament\Business\Resources\TravelAgencies\Pages\CreateTravelAgency;
use App\Filament\Business\Resources\TravelAgencies\Pages\EditTravelAgency;
use App\Filament\Business\Resources\TravelAgencies\Pages\ListTravelAgencies;
use App\Filament\Business\Resources\TravelAgencies\Schemas\TravelAgencyForm;
use App\Filament\Business\Resources\TravelAgencies\Tables\TravelAgenciesTable;
use App\Models\TravelAgency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TravelAgencyResource extends Resource
{
    protected static ?string $model = TravelAgency::class;

    protected static ?string $navigationLabel = 'Agencias De Viaje';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string | UnitEnum | null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return TravelAgencyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TravelAgenciesTable::configure($table);
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
            'index' => ListTravelAgencies::route('/'),
            'create' => CreateTravelAgency::route('/create'),
            'edit' => EditTravelAgency::route('/{record}/edit'),
        ];
    }
}
