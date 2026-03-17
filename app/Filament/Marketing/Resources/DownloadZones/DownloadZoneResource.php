<?php

namespace App\Filament\Marketing\Resources\DownloadZones;

use App\Filament\Marketing\Resources\DownloadZones\Pages\CreateDownloadZone;
use App\Filament\Marketing\Resources\DownloadZones\Pages\EditDownloadZone;
use App\Filament\Marketing\Resources\DownloadZones\Pages\ListDownloadZones;
use App\Filament\Marketing\Resources\DownloadZones\Schemas\DownloadZoneForm;
use App\Filament\Marketing\Resources\DownloadZones\Tables\DownloadZonesTable;
use App\Models\DownloadZone;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DownloadZoneResource extends Resource
{
    protected static ?string $model = DownloadZone::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Documentos';

    protected static string | UnitEnum | null $navigationGroup = 'ZONA DE DESCARGA';

    public static function form(Schema $schema): Schema
    {
        return DownloadZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DownloadZonesTable::configure($table);
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
            'index' => ListDownloadZones::route('/'),
            'create' => CreateDownloadZone::route('/create'),
            'edit' => EditDownloadZone::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'documentos')->first();

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
