<?php

namespace App\Filament\Marketing\Resources\Capemiacs;

use App\Filament\Marketing\Resources\Capemiacs\Pages\CreateCapemiac;
use App\Filament\Marketing\Resources\Capemiacs\Pages\EditCapemiac;
use App\Filament\Marketing\Resources\Capemiacs\Pages\ListCapemiacs;
use App\Filament\Marketing\Resources\Capemiacs\Pages\ViewCapemiac;
use App\Filament\Marketing\Resources\Capemiacs\Schemas\CapemiacForm;
use App\Filament\Marketing\Resources\Capemiacs\Schemas\CapemiacInfolist;
use App\Filament\Marketing\Resources\Capemiacs\Tables\CapemiacsTable;
use App\Models\Capemiac;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CapemiacResource extends Resource
{
    protected static ?string $model = Capemiac::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Capemiac';

    public static function form(Schema $schema): Schema
    {
        return CapemiacForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CapemiacInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CapemiacsTable::configure($table);
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
            'index' => ListCapemiacs::route('/'),
            'create' => CreateCapemiac::route('/create'),
            'view' => ViewCapemiac::route('/{record}'),
            'edit' => EditCapemiac::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'capemiac')->first();

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
