<?php

namespace App\Filament\Resources\CorporateQuotes;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\CorporateQuote;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\CorporateQuotes\Pages\EditCorporateQuote;
use App\Filament\Resources\CorporateQuotes\Pages\ViewCorporateQuote;
use App\Filament\Resources\CorporateQuotes\Pages\ListCorporateQuotes;
use App\Filament\Resources\CorporateQuotes\Pages\CreateCorporateQuote;
use App\Filament\Resources\CorporateQuotes\Schemas\CorporateQuoteForm;
use App\Filament\Resources\CorporateQuotes\Tables\CorporateQuotesTable;
use App\Filament\Resources\CorporateQuotes\Schemas\CorporateQuoteInfolist;
use App\Filament\Resources\CorporateQuotes\RelationManagers\StatusLogsRelationManager;
use App\Filament\Resources\CorporateQuotes\RelationManagers\DetailCoporateQuotesRelationManager;

class CorporateQuoteResource extends Resource
{
    protected static ?string $model = CorporateQuote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Wallet;

    protected static ?string $navigationLabel = 'COTIZACION CORPORATIVA';

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
            StatusLogsRelationManager::class
            
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