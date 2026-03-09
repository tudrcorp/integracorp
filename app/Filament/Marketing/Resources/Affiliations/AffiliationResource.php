<?php

namespace App\Filament\Marketing\Resources\Affiliations;

use App\Filament\Marketing\Resources\Affiliations\Pages\CreateAffiliation;
use App\Filament\Marketing\Resources\Affiliations\Pages\EditAffiliation;
use App\Filament\Marketing\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Marketing\Resources\Affiliations\Pages\ViewAffiliation;
use App\Filament\Marketing\Resources\Affiliations\Schemas\AffiliationForm;
use App\Filament\Marketing\Resources\Affiliations\Schemas\AffiliationInfolist;
use App\Filament\Marketing\Resources\Affiliations\Tables\AffiliationsTable;
use App\Models\Affiliation;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AffiliationResource extends Resource
{
    protected static ?string $model = Affiliation::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-user-plus';

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?string $navigationLabel = 'Individuales';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return AffiliationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationsTable::configure($table);
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
            'index' => ListAffiliations::route('/'),
            'create' => CreateAffiliation::route('/create'),
            'view' => ViewAffiliation::route('/{record}'),
            'edit' => EditAffiliation::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'afiliaciones-individuales')->first();

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
