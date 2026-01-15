<?php

namespace App\Filament\Business\Resources\Agencies;

use App\Filament\Business\Resources\Agencies\Pages\CreateAgency;
use App\Filament\Business\Resources\Agencies\Pages\EditAgency;
use App\Filament\Business\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Business\Resources\Agencies\Pages\ViewAgency;
use App\Filament\Business\Resources\Agencies\Schemas\AgencyForm;
use App\Filament\Business\Resources\Agencies\Schemas\AgencyInfolist;
use App\Filament\Business\Resources\Agencies\Tables\AgenciesTable;
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

    protected static ?string $navigationLabel = 'Agencias De Corretaje';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string | UnitEnum | null $navigationGroup = 'ESTRUCTURA COMERCIAL';

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