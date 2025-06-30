<?php

namespace App\Filament\General\Resources\IndividualQuotes;

use App\Filament\General\Resources\IndividualQuotes\Pages\CreateIndividualQuote;
use App\Filament\General\Resources\IndividualQuotes\Pages\EditIndividualQuote;
use App\Filament\General\Resources\IndividualQuotes\Pages\ListIndividualQuotes;
use App\Filament\General\Resources\IndividualQuotes\Pages\ViewIndividualQuote;
use App\Filament\General\Resources\IndividualQuotes\Schemas\IndividualQuoteForm;
use App\Filament\General\Resources\IndividualQuotes\Schemas\IndividualQuoteInfolist;
use App\Filament\General\Resources\IndividualQuotes\Tables\IndividualQuotesTable;
use App\Models\IndividualQuote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class IndividualQuoteResource extends Resource
{
    protected static ?string $model = IndividualQuote::class;

    protected static string | UnitEnum | null $navigationGroup = 'Cotizaciones';

    protected static ?string $navigationLabel = 'Individuales';

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
            //
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