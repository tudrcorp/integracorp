<?php

namespace App\Filament\Marketing\Resources\InfoFrees;

use App\Filament\Marketing\Resources\InfoFrees\Pages\CreateInfoFree;
use App\Filament\Marketing\Resources\InfoFrees\Pages\EditInfoFree;
use App\Filament\Marketing\Resources\InfoFrees\Pages\ListInfoFrees;
use App\Filament\Marketing\Resources\InfoFrees\Pages\ViewInfoFree;
use App\Filament\Marketing\Resources\InfoFrees\Schemas\InfoFreeForm;
use App\Filament\Marketing\Resources\InfoFrees\Schemas\InfoFreeInfolist;
use App\Filament\Marketing\Resources\InfoFrees\Tables\InfoFreesTable;
use App\Models\InfoFree;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InfoFreeResource extends Resource
{
    protected static ?string $model = InfoFree::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static ?string $navigationLabel = 'Data Externa(FREE)';

    public static function form(Schema $schema): Schema
    {
        return InfoFreeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InfoFreeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InfoFreesTable::configure($table);
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
            'index' => ListInfoFrees::route('/'),
            'create' => CreateInfoFree::route('/create'),
            'view' => ViewInfoFree::route('/{record}'),
            'edit' => EditInfoFree::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'data-externa')->first();

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
