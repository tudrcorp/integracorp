<?php

namespace App\Filament\Master\Resources\CorporateQuotes;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\CorporateQuote;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Master\Resources\CorporateQuotes\Pages\EditCorporateQuote;
use App\Filament\Master\Resources\CorporateQuotes\Pages\ViewCorporateQuote;
use App\Filament\Master\Resources\CorporateQuotes\Pages\ListCorporateQuotes;
use App\Filament\Master\Resources\CorporateQuotes\Pages\CreateCorporateQuote;
use App\Filament\Master\Resources\CorporateQuotes\Schemas\CorporateQuoteForm;
use App\Filament\Master\Resources\CorporateQuotes\Tables\CorporateQuotesTable;
use App\Filament\Master\Resources\CorporateQuotes\Schemas\CorporateQuoteInfolist;
use App\Filament\Master\Resources\CorporateQuotes\RelationManagers\DetailCoporateQuotesRelationManager;
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
            DetailCoporateQuotesRelationManager::class
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