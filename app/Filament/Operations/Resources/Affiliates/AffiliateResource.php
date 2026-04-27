<?php

namespace App\Filament\Operations\Resources\Affiliates;

use App\Filament\Operations\Resources\Affiliates\Pages\CreateAffiliate;
use App\Filament\Operations\Resources\Affiliates\Pages\EditAffiliate;
use App\Filament\Operations\Resources\Affiliates\Pages\ListAffiliates;
use App\Filament\Operations\Resources\Affiliates\Pages\ViewAffiliate;
use App\Filament\Operations\Resources\Affiliates\Schemas\AffiliateForm;
use App\Filament\Operations\Resources\Affiliates\Schemas\AffiliateInfolist;
use App\Filament\Operations\Resources\Affiliates\Tables\AffiliatesTable;
use App\Models\Affiliate;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;

    protected static ?string $navigationLabel = 'Individuales';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|UnitEnum|null $navigationGroup = 'AFILIADOS';

    protected static ?int $navigationSort = 1;

    /**
     * Muestra un badge con la palabra NEW y el conteo de afiliados
     * con estatus 'ACTIVA' registrados el día de hoy.
     */
    public static function getNavigationBadge(): ?string
    {
        $todayCount = static::getModel()::where('status', 'ACTIVO')
            ->whereDate('created_at', Carbon::today())
            ->count();

        return $todayCount > 0 ? "NUEVO {$todayCount}" : null;
    }

    /**
     * Color personalizado para el badge (Verde iOS).
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'verdeApple';
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliatesTable::configure($table);
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
            'index' => ListAffiliates::route('/'),
            'create' => CreateAffiliate::route('/create'),
            'view' => ViewAffiliate::route('/{record}'),
            'edit' => EditAffiliate::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'afiliados-individuales')->first();

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
