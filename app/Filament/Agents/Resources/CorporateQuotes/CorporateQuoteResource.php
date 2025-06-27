<?php

namespace App\Filament\Agents\Resources\CorporateQuotes;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\CorporateQuote;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Agents\Resources\CorporateQuotes\Pages\EditCorporateQuote;
use App\Filament\Agents\Resources\CorporateQuotes\Pages\ViewCorporateQuote;
use App\Filament\Agents\Resources\CorporateQuotes\Pages\ListCorporateQuotes;
use App\Filament\Agents\Resources\CorporateQuotes\Pages\CreateCorporateQuote;
use App\Filament\Agents\Resources\CorporateQuotes\Schemas\CorporateQuoteForm;
use App\Filament\Agents\Resources\CorporateQuotes\Tables\CorporateQuotesTable;

use App\Filament\Agents\Resources\CorporateQuotes\Schemas\CorporateQuoteInfolist;
use App\Filament\Agents\Resources\CorporateQuotes\RelationManagers\DetailCoporateQuotesRelationManager;

class CorporateQuoteResource extends Resource
{
    protected static ?string $model = CorporateQuote::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-swatch';

    protected static string | UnitEnum | null $navigationGroup = 'COTIZACIONES';

    protected static ?string $navigationLabel = 'CORPORATIVAS';

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
            DetailCoporateQuotesRelationManager::class,
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