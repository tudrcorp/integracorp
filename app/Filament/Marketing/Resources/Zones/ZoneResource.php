<?php

namespace App\Filament\Marketing\Resources\Zones;

use App\Filament\Marketing\Resources\Zones\Pages\CreateZone;
use App\Filament\Marketing\Resources\Zones\Pages\EditZone;
use App\Filament\Marketing\Resources\Zones\Pages\ListZones;
use App\Filament\Marketing\Resources\Zones\Schemas\ZoneForm;
use App\Filament\Marketing\Resources\Zones\Tables\ZonesTable;
use App\Models\Permission;
use App\Models\UserPermission;
use App\Models\Zone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Gestión de Carpetas';

    protected static string | UnitEnum | null $navigationGroup = 'ZONA DE DESCARGA';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZonesTable::configure($table);
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
            'index' => ListZones::route('/'),
            'create' => CreateZone::route('/create'),
            'edit' => EditZone::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'gestion-de-carpetas')->first();

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
