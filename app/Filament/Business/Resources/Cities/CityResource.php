<?php

namespace App\Filament\Business\Resources\Cities;

use App\Filament\Business\Resources\Cities\Pages\CreateCity;
use App\Filament\Business\Resources\Cities\Pages\EditCity;
use App\Filament\Business\Resources\Cities\Pages\ListCities;
use App\Filament\Business\Resources\Cities\Pages\ViewCity;
use App\Filament\Business\Resources\Cities\Schemas\CityForm;
use App\Filament\Business\Resources\Cities\Schemas\CityInfolist;
use App\Filament\Business\Resources\Cities\Tables\CitiesTable;
use App\Models\City;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $navigationLabel = 'Ciudades';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return CityForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CityInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CitiesTable::configure($table);
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
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'view' => ViewCity::route('/{record}'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }
}