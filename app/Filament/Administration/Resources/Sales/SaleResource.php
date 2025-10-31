<?php

namespace App\Filament\Administration\Resources\Sales;

use App\Filament\Administration\Resources\Sales\Pages\CreateSale;
use App\Filament\Administration\Resources\Sales\Pages\EditSale;
use App\Filament\Administration\Resources\Sales\Pages\ListSales;
use App\Filament\Administration\Resources\Sales\Schemas\SaleForm;
use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Models\Sale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Scale;

    protected static string | UnitEnum | null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Ventas';

    public static function form(Schema $schema): Schema
    {
        return SaleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
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
            'index' => ListSales::route('/'),
            'create' => CreateSale::route('/create'),
            'edit' => EditSale::route('/{record}/edit'),
        ];
    }
}
