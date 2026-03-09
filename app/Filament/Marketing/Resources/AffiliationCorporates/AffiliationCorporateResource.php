<?php

namespace App\Filament\Marketing\Resources\AffiliationCorporates;

use App\Filament\Marketing\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate;
use App\Filament\Marketing\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate;
use App\Filament\Marketing\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Marketing\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Marketing\Resources\AffiliationCorporates\Schemas\AffiliationCorporateForm;
use App\Filament\Marketing\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use App\Filament\Marketing\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;
use App\Models\AffiliationCorporate;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AffiliationCorporateResource extends Resource
{
    protected static ?string $model = AffiliationCorporate::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?string $navigationLabel = 'Corporativas';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AffiliationCorporateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationCorporateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationCorporatesTable::configure($table);
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
            'index' => ListAffiliationCorporates::route('/'),
            'create' => CreateAffiliationCorporate::route('/create'),
            'view' => ViewAffiliationCorporate::route('/{record}'),
            'edit' => EditAffiliationCorporate::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'afiliaciones-corporativas')->first();

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
