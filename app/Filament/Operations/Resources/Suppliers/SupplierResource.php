<?php

namespace App\Filament\Operations\Resources\Suppliers;

use BackedEnum;
use App\Models\Supplier;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Operations\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Operations\Resources\Suppliers\Pages\ViewSupplier;
use App\Filament\Operations\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Operations\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Operations\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Operations\Resources\Suppliers\Tables\SuppliersTable;
use App\Filament\Operations\Resources\Suppliers\Schemas\SupplierInfolist;
use App\Filament\Operations\Resources\Suppliers\RelationManagers\SupplierRedGlobalsRelationManager;
use App\Filament\Operations\Resources\Suppliers\RelationManagers\SupplierObservacionsRelationManager;
use App\Filament\Operations\Resources\Suppliers\RelationManagers\SupplierZonaCoberturasRelationManager;
use App\Filament\Operations\Resources\Suppliers\RelationManagers\SupplierContactPrincipalsRelationManager;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationLabel = 'Proveedores';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

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
}