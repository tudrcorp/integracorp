<?php

namespace App\Filament\Resources\CheckSales;

use App\Filament\Resources\CheckSales\Pages\CreateCheckSale;
use App\Filament\Resources\CheckSales\Pages\EditCheckSale;
use App\Filament\Resources\CheckSales\Pages\ListCheckSales;
use App\Filament\Resources\CheckSales\Pages\ViewCheckSale;
use App\Filament\Resources\CheckSales\Schemas\CheckSaleForm;
use App\Filament\Resources\CheckSales\Schemas\CheckSaleInfolist;
use App\Filament\Resources\CheckSales\Tables\CheckSalesTable;
use App\Models\CheckSale;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CheckSaleResource extends Resource
{
    protected static ?string $model = CheckSale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'HISTORICOS';

    protected static ?string $navigationLabel = 'Ventas';

    public static function form(Schema $schema): Schema
    {
        return CheckSaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CheckSaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CheckSalesTable::configure($table);
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
            'index' => ListCheckSales::route('/'),
            'create' => CreateCheckSale::route('/create'),
            'view' => ViewCheckSale::route('/{record}'),
            'edit' => EditCheckSale::route('/{record}/edit'),
        ];
    }
}