<?php

namespace App\Filament\Business\Resources\Countries;

use App\Filament\Business\Resources\Countries\Pages\CreateCountry;
use App\Filament\Business\Resources\Countries\Pages\EditCountry;
use App\Filament\Business\Resources\Countries\Pages\ListCountries;
use App\Filament\Business\Resources\Countries\Pages\ViewCountry;
use App\Filament\Business\Resources\Countries\Schemas\CountryForm;
use App\Filament\Business\Resources\Countries\Schemas\CountryInfolist;
use App\Filament\Business\Resources\Countries\Tables\CountriesTable;
use App\Models\Country;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CountryResource extends Resource
{
    protected static ?string $model = Country::class;

    protected static ?string $navigationLabel = 'Paises';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return CountryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CountryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CountriesTable::configure($table);
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
            'index' => ListCountries::route('/'),
            'create' => CreateCountry::route('/create'),
            'view' => ViewCountry::route('/{record}'),
            'edit' => EditCountry::route('/{record}/edit'),
        ];
    }
}