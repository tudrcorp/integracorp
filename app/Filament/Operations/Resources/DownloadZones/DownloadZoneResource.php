<?php

namespace App\Filament\Operations\Resources\DownloadZones;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\DownloadZones\Pages\CreateDownloadZone;
use App\Filament\Operations\Resources\DownloadZones\Pages\EditDownloadZone;
use App\Filament\Operations\Resources\DownloadZones\Pages\ListDownloadZones;
use App\Filament\Operations\Resources\DownloadZones\Schemas\DownloadZoneForm;
use App\Filament\Operations\Resources\DownloadZones\Tables\DownloadZonesTable;
use App\Models\DownloadZone;
use App\Models\Permission;
use App\Models\UserPermission;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DownloadZoneResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = DownloadZone::class;

    protected static ?string $navigationLabel = 'Documentos';

    protected static string|UnitEnum|null $navigationGroup = 'ZONA DE DESCARGA';

    protected static ?int $navigationSort = 99;

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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDownloadZones::route('/'),
            'create' => CreateDownloadZone::route('/create'),
            'edit' => EditDownloadZone::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'documentos-descarga')->first();

    //     // si es superadmin, retornar true
    //     if (in_array('SUPERADMIN', Auth::user()->departament)) {
    //         return true;
    //     }

    //     if (in_array($module, Auth::user()->departament)) {
    //         if (UserPermission::where('user_id', Auth::user()->id)->where('permission_id', $permission->id)->exists()) {
    //             return true;
    //         }
    //     }

    //     return false;
    // }
}
