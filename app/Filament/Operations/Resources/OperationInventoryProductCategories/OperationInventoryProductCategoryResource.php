<?php

namespace App\Filament\Operations\Resources\OperationInventoryProductCategories;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationInventoryProductCategories\Pages\CreateOperationInventoryProductCategory;
use App\Filament\Operations\Resources\OperationInventoryProductCategories\Pages\EditOperationInventoryProductCategory;
use App\Filament\Operations\Resources\OperationInventoryProductCategories\Pages\ListOperationInventoryProductCategories;
use App\Filament\Operations\Resources\OperationInventoryProductCategories\Pages\ViewOperationInventoryProductCategory;
use App\Filament\Operations\Resources\OperationInventoryProductCategories\Schemas\OperationInventoryProductCategoryForm;
use App\Filament\Operations\Resources\OperationInventoryProductCategories\Schemas\OperationInventoryProductCategoryInfolist;
use App\Filament\Operations\Resources\OperationInventoryProductCategories\Tables\OperationInventoryProductCategoriesTable;
use App\Models\OperationInventoryProductCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OperationInventoryProductCategoryResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationInventoryProductCategory::class;

    protected static ?string $navigationLabel = 'Categorías';

    protected static ?string $modelLabel = 'Categoría';

    protected static ?string $pluralModelLabel = 'Categorías';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'INVENTARIO DIAGNOMOVIL';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return OperationInventoryProductCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationInventoryProductCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationInventoryProductCategoriesTable::configure($table);
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
            'index' => ListOperationInventoryProductCategories::route('/'),
            'create' => CreateOperationInventoryProductCategory::route('/create'),
            'view' => ViewOperationInventoryProductCategory::route('/{record}'),
            'edit' => EditOperationInventoryProductCategory::route('/{record}/edit'),
        ];
    }
}
