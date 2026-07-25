<?php

namespace App\Filament\Operations\Resources\OperationInventoryProducts;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationInventoryProducts\Pages\CreateOperationInventoryProduct;
use App\Filament\Operations\Resources\OperationInventoryProducts\Pages\EditOperationInventoryProduct;
use App\Filament\Operations\Resources\OperationInventoryProducts\Pages\ListOperationInventoryProducts;
use App\Filament\Operations\Resources\OperationInventoryProducts\Pages\ViewOperationInventoryProduct;
use App\Filament\Operations\Resources\OperationInventoryProducts\Schemas\OperationInventoryProductForm;
use App\Filament\Operations\Resources\OperationInventoryProducts\Schemas\OperationInventoryProductInfolist;
use App\Filament\Operations\Resources\OperationInventoryProducts\Tables\OperationInventoryProductsTable;
use App\Models\OperationInventoryProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OperationInventoryProductResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationInventoryProduct::class;

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = 'INVENTARIO DIAGNOMOVIL';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return OperationInventoryProductForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationInventoryProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationInventoryProductsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['category'])
            ->withSum('stocks', 'existence');
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
            'index' => ListOperationInventoryProducts::route('/'),
            'create' => CreateOperationInventoryProduct::route('/create'),
            'view' => ViewOperationInventoryProduct::route('/{record}'),
            'edit' => EditOperationInventoryProduct::route('/{record}/edit'),
        ];
    }
}
