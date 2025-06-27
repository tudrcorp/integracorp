<?php

namespace App\Filament\Resources\BusinessUnits;

use BackedEnum;
use Filament\Tables\Table;
use App\Models\BusinessUnit;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\BusinessUnits\Pages\EditBusinessUnit;
use App\Filament\Resources\BusinessUnits\Pages\ViewBusinessUnit;
use App\Filament\Resources\BusinessUnits\Pages\ListBusinessUnits;
use App\Filament\Resources\BusinessUnits\Pages\CreateBusinessUnit;
use App\Filament\Resources\BusinessUnits\Schemas\BusinessUnitForm;
use App\Filament\Resources\BusinessUnits\Tables\BusinessUnitsTable;
use App\Filament\Resources\BusinessUnits\Schemas\BusinessUnitInfolist;
use App\Filament\Resources\BusinessUnits\RelationManagers\BusinessLineRelationManager;

class BusinessUnitResource extends Resource
{
    protected static ?string $model = BusinessUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::RectangleGroup;

    protected static ?string $navigationLabel = 'UNIDADES DE NEGOCIO';

    public static function form(Schema $schema): Schema
    {
        return BusinessUnitForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BusinessUnitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessUnitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BusinessLineRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessUnits::route('/'),
            'create' => CreateBusinessUnit::route('/create'),
            'view' => ViewBusinessUnit::route('/{record}'),
            'edit' => EditBusinessUnit::route('/{record}/edit'),
        ];
    }
}