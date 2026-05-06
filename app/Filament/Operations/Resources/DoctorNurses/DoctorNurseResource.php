<?php

namespace App\Filament\Operations\Resources\DoctorNurses;

use App\Filament\Operations\Resources\DoctorNurses\Pages\CreateDoctorNurse;
use App\Filament\Operations\Resources\DoctorNurses\Pages\EditDoctorNurse;
use App\Filament\Operations\Resources\DoctorNurses\Pages\ListDoctorNurses;
use App\Filament\Operations\Resources\DoctorNurses\Pages\ViewDoctorNurse;
use App\Filament\Operations\Resources\DoctorNurses\RelationManagers\DoctorNurseObservacionsRelationManager;
use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseForm;
use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseInfolist;
use App\Filament\Operations\Resources\DoctorNurses\Tables\DoctorNursesTable;
use App\Models\DoctorNurse;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DoctorNurseResource extends Resource
{
    protected static ?string $model = DoctorNurse::class;

    protected static ?string $navigationLabel = 'Proveedores Naturales';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return DoctorNurseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DoctorNurseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorNursesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplierClasificacion', 'doctorNurseObservacions']);
    }

    public static function getRelations(): array
    {
        return [
            DoctorNurseObservacionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoctorNurses::route('/'),
            'create' => CreateDoctorNurse::route('/create'),
            'view' => ViewDoctorNurse::route('/{record}'),
            'edit' => EditDoctorNurse::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'proveedores-naturales')->first();

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
