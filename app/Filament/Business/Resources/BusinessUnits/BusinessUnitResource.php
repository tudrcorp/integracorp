<?php

namespace App\Filament\Business\Resources\BusinessUnits;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use App\Models\BusinessUnit;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Business\Resources\BusinessUnits\Pages\EditBusinessUnit;
use App\Filament\Business\Resources\BusinessUnits\Pages\ViewBusinessUnit;
use App\Filament\Business\Resources\BusinessUnits\Pages\ListBusinessUnits;
use App\Filament\Business\Resources\BusinessUnits\Pages\CreateBusinessUnit;
use App\Filament\Business\Resources\BusinessUnits\Schemas\BusinessUnitForm;
use App\Filament\Business\Resources\BusinessUnits\Tables\BusinessUnitsTable;
use App\Filament\Business\Resources\BusinessUnits\Schemas\BusinessUnitInfolist;

class BusinessUnitResource extends Resource
{
    protected static ?string $model = BusinessUnit::class;

    protected static ?string $navigationLabel = 'Unidades de Negocio';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

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
            //
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

    public static function shouldRegisterNavigation(): bool
    {
        //Solo el Administrador General del Modulo de Business puede acceder a este recurso
        if (in_array('SUPERADMIN', auth()->user()->departament)) {
            return true;
        }
        return false;
    }
}
