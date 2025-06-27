<?php

namespace App\Filament\Resources\AgencyTypes;

use App\Filament\Resources\AgencyTypes\Pages\CreateAgencyType;
use App\Filament\Resources\AgencyTypes\Pages\EditAgencyType;
use App\Filament\Resources\AgencyTypes\Pages\ListAgencyTypes;
use App\Filament\Resources\AgencyTypes\Pages\ViewAgencyType;
use App\Filament\Resources\AgencyTypes\Schemas\AgencyTypeForm;
use App\Filament\Resources\AgencyTypes\Schemas\AgencyTypeInfolist;
use App\Filament\Resources\AgencyTypes\Tables\AgencyTypesTable;
use App\Models\AgencyType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AgencyTypeResource extends Resource
{
    protected static ?string $model = AgencyType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice2;

    protected static ?string $navigationLabel = 'TIPO AGENCIAS';

    public static function form(Schema $schema): Schema
    {
        return AgencyTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AgencyTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgencyTypesTable::configure($table);
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
            'index' => ListAgencyTypes::route('/'),
            'create' => CreateAgencyType::route('/create'),
            'view' => ViewAgencyType::route('/{record}'),
            'edit' => EditAgencyType::route('/{record}/edit'),
        ];
    }
}