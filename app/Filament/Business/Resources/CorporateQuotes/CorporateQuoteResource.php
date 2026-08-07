<?php

namespace App\Filament\Business\Resources\CorporateQuotes;

use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Business\Resources\CorporateQuotes\Pages\CreateCorporateQuote;
use App\Filament\Business\Resources\CorporateQuotes\Pages\EditCorporateQuote;
use App\Filament\Business\Resources\CorporateQuotes\Pages\ListCorporateQuotes;
use App\Filament\Business\Resources\CorporateQuotes\Pages\ViewCorporateQuote;
use App\Filament\Business\Resources\CorporateQuotes\RelationManagers\CorporateQuoteDataRelationManager;
use App\Filament\Business\Resources\CorporateQuotes\RelationManagers\DetailCoporateQuotesRelationManager;
use App\Filament\Business\Resources\CorporateQuotes\Schemas\CorporateQuoteForm;
use App\Filament\Business\Resources\CorporateQuotes\Schemas\CorporateQuoteInfolist;
use App\Filament\Business\Resources\CorporateQuotes\Tables\CorporateQuotesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\CorporateQuote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CorporateQuoteResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = CorporateQuote::class;

    protected static ?string $navigationLabel = 'Corporativas';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|UnitEnum|null $navigationGroup = 'COTIZACIONES';

    protected static ?string $recordTitleAttribute = 'code';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 60;

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return ['id', 'code', 'full_name', 'rif', 'email', 'phone', 'code_agency', 'status'];
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
        return ['rif'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof CorporateQuote) {
            return [];
        }

        return [
            'Solicitante' => filled($record->full_name) ? (string) $record->full_name : '—',
            'RIF' => filled($record->rif) ? (string) $record->rif : '—',
            'Agencia' => filled($record->code_agency) ? (string) $record->code_agency : '—',
            'Estatus' => filled($record->status) ? (string) $record->status : '—',
        ];
    }

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
            CorporateQuoteDataRelationManager::class,
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
