<?php

namespace App\Filament\General\Resources\CorporateQuotes;

use App\Filament\General\Resources\CorporateQuotes\Pages\CreateCorporateQuote;
use App\Filament\General\Resources\CorporateQuotes\Pages\EditCorporateQuote;
use App\Filament\General\Resources\CorporateQuotes\Pages\ListCorporateQuotes;
use App\Filament\General\Resources\CorporateQuotes\Pages\ViewCorporateQuote;
use App\Filament\General\Resources\CorporateQuotes\Schemas\CorporateQuoteForm;
use App\Filament\General\Resources\CorporateQuotes\Schemas\CorporateQuoteInfolist;
use App\Filament\General\Resources\CorporateQuotes\Tables\CorporateQuotesTable;
use App\Models\CorporateQuote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CorporateQuoteResource extends Resource
{
    protected static ?string $model = CorporateQuote::class;

    protected static string | UnitEnum | null $navigationGroup = 'CORPORATIVAS';

    protected static ?string $navigationLabel = 'Cotizar';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CorporateQuoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CorporateQuoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CorporateQuotesTable::configure($table);
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
            'index' => ListCorporateQuotes::route('/'),
            'create' => CreateCorporateQuote::route('/create'),
            'view' => ViewCorporateQuote::route('/{record}'),
            'edit' => EditCorporateQuote::route('/{record}/edit'),
        ];
    }
}