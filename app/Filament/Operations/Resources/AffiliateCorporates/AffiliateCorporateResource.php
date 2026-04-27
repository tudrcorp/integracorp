<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates;

use App\Filament\Operations\Resources\AffiliateCorporates\Pages\CreateAffiliateCorporate;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\EditAffiliateCorporate;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\ListAffiliateCorporates;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\ViewAffiliateCorporate;
use App\Filament\Operations\Resources\AffiliateCorporates\Schemas\AffiliateCorporateForm;
use App\Filament\Operations\Resources\AffiliateCorporates\Schemas\AffiliateCorporateInfolist;
use App\Filament\Operations\Resources\AffiliateCorporates\Tables\AffiliateCorporatesTable;
use App\Models\AffiliateCorporate;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AffiliateCorporateResource extends Resource
{
    protected static ?string $model = AffiliateCorporate::class;

    protected static ?string $navigationLabel = 'Corporativos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'AFILIADOS';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AffiliateCorporateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateCorporateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateCorporatesTable::configure($table);
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
            'index' => ListAffiliateCorporates::route('/'),
            'create' => CreateAffiliateCorporate::route('/create'),
            'view' => ViewAffiliateCorporate::route('/{record}'),
            'edit' => EditAffiliateCorporate::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'afiliados-corporativos')->first();

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
