<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\CreateOperationCoordinationService;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\EditOperationCoordinationService;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ListOperationCoordinationServices;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceQuotes;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ViewOperationCoordinationService;
use App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers\TelemedicinePatientLabsRelationManager;
use App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers\TelemedicinePatientMedicationsRelationManager;
use App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers\TelemedicinePatientSpecialtiesRelationManager;
use App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers\TelemedicinePatientStudiesRelationManager;
use App\Filament\Operations\Resources\OperationCoordinationServices\Schemas\OperationCoordinationServiceForm;
use App\Filament\Operations\Resources\OperationCoordinationServices\Schemas\OperationCoordinationServiceInfolist;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Models\OperationCoordinationService;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OperationCoordinationServiceResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationCoordinationService::class;

    protected static ?string $navigationLabel = 'Servicios Medicos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    public static function getNavigationBadge(): ?string
    {
        return OperationCoordinationService::whereDate('created_at', Carbon::today())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return OperationCoordinationService::whereDate('created_at', Carbon::today())->count() > 0 ? 'success' : 'gray';
    }

    public static function form(Schema $schema): Schema
    {
        return OperationCoordinationServiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationCoordinationServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationCoordinationServicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
            TelemedicinePatientMedicationsRelationManager::class,
            TelemedicinePatientLabsRelationManager::class,
            TelemedicinePatientStudiesRelationManager::class,
            TelemedicinePatientSpecialtiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationCoordinationServices::route('/'),
            'create' => CreateOperationCoordinationService::route('/create'),
            'view' => ViewOperationCoordinationService::route('/{record}'),
            'manage-quotes' => ManageCoordinationServiceQuotes::route('/{record}/manage-quotes'),
            'manage-items' => ManageCoordinationServiceItems::route('/{record}/manage-items'),
            'edit' => EditOperationCoordinationService::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'coordinacion-de-servicios')->first();

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
