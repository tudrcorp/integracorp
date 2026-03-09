<?php

namespace App\Filament\Marketing\Resources\TravelAgencies;

use App\Filament\Marketing\Resources\TravelAgencies\Pages\CreateTravelAgency;
use App\Filament\Marketing\Resources\TravelAgencies\Pages\EditTravelAgency;
use App\Filament\Marketing\Resources\TravelAgencies\Pages\ListTravelAgencies;
use App\Filament\Marketing\Resources\TravelAgencies\Pages\ViewTravelAgency;
use App\Filament\Marketing\Resources\TravelAgencies\Schemas\TravelAgencyForm;
use App\Filament\Marketing\Resources\TravelAgencies\Schemas\TravelAgencyInfolist;
use App\Filament\Marketing\Resources\TravelAgencies\Tables\TravelAgenciesTable;
use App\Models\Permission;
use App\Models\TravelAgency;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TravelAgencyResource extends Resource
{
    protected static ?string $model = TravelAgency::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|UnitEnum|null $navigationGroup = 'ESTRUCTURA DE VIAJES';

    protected static ?string $navigationLabel = 'Agencias De Viajes';

    // protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return TravelAgencyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TravelAgencyInfolist::configure($schema);
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
            'view' => ViewTravelAgency::route('/{record}'),
            'edit' => EditTravelAgency::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'agencias-de-viaje')->first();

        // si es superadmin, retornar true
        if (in_array('SUPERADMIN', Auth::user()->departament)) {
            return true;
        }

        if (in_array($module, Auth::user()->departament)) {
            if (UserPermission::where('user_id', Auth::user()->id)->where('permission_id', $permission->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
