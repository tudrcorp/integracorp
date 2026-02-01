<?php

namespace App\Filament\Business\Resources\Regions;

use UnitEnum;
use BackedEnum;
use App\Models\Region;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Business\Resources\Regions\Pages\EditRegion;
use App\Filament\Business\Resources\Regions\Pages\ViewRegion;
use App\Filament\Business\Resources\Regions\Pages\ListRegions;
use App\Filament\Business\Resources\Regions\Pages\CreateRegion;
use App\Filament\Business\Resources\Regions\Schemas\RegionForm;
use App\Filament\Business\Resources\Regions\Tables\RegionsTable;
use App\Filament\Business\Resources\Regions\Schemas\RegionInfolist;

class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static ?string $navigationLabel = 'Regiones';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return RegionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RegionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegionsTable::configure($table);
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
            'index' => ListRegions::route('/'),
            'create' => CreateRegion::route('/create'),
            'view' => ViewRegion::route('/{record}'),
            'edit' => EditRegion::route('/{record}/edit'),
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
