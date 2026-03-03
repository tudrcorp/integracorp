<?php

namespace App\Filament\Business\Resources\DressTylorQuotes;

use App\Filament\Business\Resources\DressTylorQuotes\Pages\CreateDressTylorQuote;
use App\Filament\Business\Resources\DressTylorQuotes\Pages\EditDressTylorQuote;
use App\Filament\Business\Resources\DressTylorQuotes\Pages\ListDressTylorQuotes;
use App\Filament\Business\Resources\DressTylorQuotes\Pages\ViewDressTylorQuote;
use App\Filament\Business\Resources\DressTylorQuotes\Schemas\DressTylorQuoteForm;
use App\Filament\Business\Resources\DressTylorQuotes\Schemas\DressTylorQuoteInfolist;
use App\Filament\Business\Resources\DressTylorQuotes\Tables\DressTylorQuotesTable;
use App\Models\DressTylorQuote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class DressTylorQuoteResource extends Resource
{
    protected static ?string $model = DressTylorQuote::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|UnitEnum|null $navigationGroup = 'COTIZACIONES';

    protected static ?string $navigationLabel = 'Cotizador';

    public static function form(Schema $schema): Schema
    {
        return DressTylorQuoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DressTylorQuoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DressTylorQuotesTable::configure($table);
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
            'index' => ListDressTylorQuotes::route('/'),
            'create' => CreateDressTylorQuote::route('/create'),
            'view' => ViewDressTylorQuote::route('/{record}'),
            'edit' => EditDressTylorQuote::route('/{record}/edit'),
        ];
    }
}
