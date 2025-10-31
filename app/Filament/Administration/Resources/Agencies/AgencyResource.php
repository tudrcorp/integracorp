<?php

namespace App\Filament\Administration\Resources\Agencies;

use App\Filament\Administration\Resources\Agencies\Pages\CreateAgency;
use App\Filament\Administration\Resources\Agencies\Pages\EditAgency;
use App\Filament\Administration\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Administration\Resources\Agencies\Schemas\AgencyForm;
use App\Filament\Administration\Resources\Agencies\Tables\AgenciesTable;
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

    protected static ?string $navigationLabel = 'Agencias';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string | UnitEnum | null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    public static function form(Schema $schema): Schema
    {
        return AgencyForm::configure($schema);
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
            'edit' => EditAgency::route('/{record}/edit'),
        ];
    }
}
