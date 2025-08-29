<?php

namespace App\Filament\Agents\Resources\Sales;

use App\Filament\Agents\Resources\Sales\Pages\CreateSale;
use App\Filament\Agents\Resources\Sales\Pages\EditSale;
use App\Filament\Agents\Resources\Sales\Pages\ListSales;
use App\Filament\Agents\Resources\Sales\Pages\ViewSale;
use App\Filament\Agents\Resources\Sales\Schemas\SaleForm;
use App\Filament\Agents\Resources\Sales\Schemas\SaleInfolist;
use App\Filament\Agents\Resources\Sales\Tables\SalesTable;
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

    protected static ?string $navigationLabel = 'Ventas Realizadas';

    protected static string | UnitEnum | null $navigationGroup = 'VENTAS';

    protected static ?int $navigationSort = 1;

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