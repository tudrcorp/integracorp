<?php

namespace App\Filament\Marketing\Resources\Agencies;

use App\Filament\Marketing\Resources\Agencies\Pages\CreateAgency;
use App\Filament\Marketing\Resources\Agencies\Pages\EditAgency;
use App\Filament\Marketing\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Marketing\Resources\Agencies\Pages\ViewAgency;
use App\Filament\Marketing\Resources\Agencies\Schemas\AgencyForm;
use App\Filament\Marketing\Resources\Agencies\Schemas\AgencyInfolist;
use App\Filament\Marketing\Resources\Agencies\Tables\AgenciesTable;
use App\Models\Agency;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-c-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'ESTRUCTURA DE CORRETAJES';

    protected static ?string $navigationLabel = 'Agencias De Corretajes';

    // protected static ?int $navigationSort = 1;

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

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'agencias-de-corretaje')->first();

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
