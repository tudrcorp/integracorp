<?php

namespace App\Filament\Business\Resources\IndividualQuotes;

use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Business\Resources\IndividualQuotes\Pages\CreateIndividualQuote;
use App\Filament\Business\Resources\IndividualQuotes\Pages\EditIndividualQuote;
use App\Filament\Business\Resources\IndividualQuotes\Pages\ListIndividualQuotes;
use App\Filament\Business\Resources\IndividualQuotes\Pages\ViewIndividualQuote;
use App\Filament\Business\Resources\IndividualQuotes\RelationManagers\DetailsQuoteRelationManager;
use App\Filament\Business\Resources\IndividualQuotes\Schemas\IndividualQuoteForm;
use App\Filament\Business\Resources\IndividualQuotes\Schemas\IndividualQuoteInfolist;
use App\Filament\Business\Resources\IndividualQuotes\Tables\IndividualQuotesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\IndividualQuote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class IndividualQuoteResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = IndividualQuote::class;

    protected static ?string $navigationLabel = 'Individuales';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'COTIZACIONES';

    protected static ?string $recordTitleAttribute = 'code';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 50;

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return ['id', 'code', 'full_name', 'email', 'phone', 'code_agency', 'status'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchTextColumns(): array
    {
        return ['full_name', 'email'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchCodeColumns(): array
    {
        return ['code', 'code_agency', 'owner_code'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchDocumentColumns(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof IndividualQuote) {
            return [];
        }

        return [
            'Solicitante' => filled($record->full_name) ? (string) $record->full_name : '—',
            'Agencia' => filled($record->code_agency) ? (string) $record->code_agency : '—',
            'Estatus' => filled($record->status) ? (string) $record->status : '—',
        ];
    }

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
            DetailsQuoteRelationManager::class,
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
