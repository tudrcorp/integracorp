<?php

namespace App\Filament\General\Resources\Sales;

use App\Filament\General\Resources\Sales\Pages\CreateSale;
use App\Filament\General\Resources\Sales\Pages\EditSale;
use App\Filament\General\Resources\Sales\Pages\ListSales;
use App\Filament\General\Resources\Sales\Pages\ViewSale;
use App\Filament\General\Resources\Sales\Schemas\SaleForm;
use App\Filament\General\Resources\Sales\Schemas\SaleInfolist;
use App\Filament\General\Resources\Sales\Tables\SalesTable;
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

    protected static string | UnitEnum | null $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Ventas';

    public static function form(Schema $schema): Schema
    {
        return SaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SaleInfolist::configure($schema);
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
            'view' => ViewSale::route('/{record}'),
            'edit' => EditSale::route('/{record}/edit'),
        ];
    }
}