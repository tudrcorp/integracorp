<?php

namespace App\Filament\Agents\Resources\IndividualQuotes;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use App\Models\IndividualQuote;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Agents\Resources\IndividualQuotes\Pages\EditIndividualQuote;
use App\Filament\Agents\Resources\IndividualQuotes\Pages\ViewIndividualQuote;
use App\Filament\Agents\Resources\IndividualQuotes\Pages\ListIndividualQuotes;
use App\Filament\Agents\Resources\IndividualQuotes\Pages\CreateIndividualQuote;
use App\Filament\Agents\Resources\IndividualQuotes\Schemas\IndividualQuoteForm;
use App\Filament\Agents\Resources\IndividualQuotes\Tables\IndividualQuotesTable;

use App\Filament\Agents\Resources\IndividualQuotes\Schemas\IndividualQuoteInfolist;


class IndividualQuoteResource extends Resource
{
    protected static ?string $model = IndividualQuote::class;

    protected static string | UnitEnum | null $navigationGroup = 'INDIVIDUALES';

    protected static ?string $navigationLabel = 'Cotizar';

    protected static ?int $navigationSort = 1;

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
            RelationManagers\DetailsQuoteRelationManager::class
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