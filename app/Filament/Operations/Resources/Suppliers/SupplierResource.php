<?php

namespace App\Filament\Operations\Resources\Suppliers;

use App\Filament\Operations\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Operations\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Operations\Resources\Suppliers\Pages\ViewSupplier;
use App\Filament\Operations\Resources\Suppliers\RelationManagers\SupplierContactPrincipalsRelationManager;
use App\Filament\Operations\Resources\Suppliers\RelationManagers\SupplierObservacionsRelationManager;
use App\Filament\Operations\Resources\Suppliers\RelationManagers\SupplierRedGlobalsRelationManager;
use App\Filament\Operations\Resources\Suppliers\RelationManagers\SupplierZonaCoberturasRelationManager;
use App\Filament\Operations\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Operations\Resources\Suppliers\Schemas\SupplierInfolist;
use App\Filament\Operations\Resources\Suppliers\Tables\SuppliersTable;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationLabel = 'Proveedores Jurídicos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupplierInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['state', 'city']);
    }

    public static function getRelations(): array
    {
        return [
            //
            SupplierContactPrincipalsRelationManager::class,
            SupplierRedGlobalsRelationManager::class,
            SupplierZonaCoberturasRelationManager::class,
            SupplierObservacionsRelationManager::class,

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'view' => ViewSupplier::route('/{record}'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'proveedores-juridicos')->first();

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
