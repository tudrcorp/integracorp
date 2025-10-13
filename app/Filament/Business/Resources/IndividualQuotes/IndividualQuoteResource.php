<?php

namespace App\Filament\Business\Resources\IndividualQuotes;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\IndividualQuote;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Business\Resources\IndividualQuotes\Pages\EditIndividualQuote;
use App\Filament\Business\Resources\IndividualQuotes\Pages\ViewIndividualQuote;
use App\Filament\Business\Resources\IndividualQuotes\Pages\ListIndividualQuotes;
use App\Filament\Business\Resources\IndividualQuotes\Pages\CreateIndividualQuote;
use App\Filament\Business\Resources\IndividualQuotes\Schemas\IndividualQuoteForm;
use App\Filament\Business\Resources\IndividualQuotes\Tables\IndividualQuotesTable;
use App\Filament\Business\Resources\IndividualQuotes\Schemas\IndividualQuoteInfolist;
use App\Filament\Business\Resources\IndividualQuotes\RelationManagers\DetailsQuoteRelationManager;

class IndividualQuoteResource extends Resource
{
    protected static ?string $model = IndividualQuote::class;

    protected static ?string $navigationLabel = 'Individuales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'COTIZACIONES';

    public static function form(Schema $schema): Schema
    {
        return IndividualQuoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IndividualQuoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndividualQuotesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DetailsQuoteRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndividualQuotes::route('/'),
            'create' => CreateIndividualQuote::route('/create'),
            'view' => ViewIndividualQuote::route('/{record}'),
            'edit' => EditIndividualQuote::route('/{record}/edit'),
        ];
    }
}