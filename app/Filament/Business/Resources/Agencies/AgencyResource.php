<?php

namespace App\Filament\Business\Resources\Agencies;

use App\Filament\Business\Resources\Agencies\Pages\CreateAgency;
use App\Filament\Business\Resources\Agencies\Pages\EditAgency;
use App\Filament\Business\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Business\Resources\Agencies\Pages\ViewAgency;
use App\Filament\Business\Resources\Agencies\Schemas\AgencyForm;
use App\Filament\Business\Resources\Agencies\Schemas\AgencyInfolist;
use App\Filament\Business\Resources\Agencies\Tables\AgenciesTable;
use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\Agency;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AgencyResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = Agency::class;

    protected static ?string $navigationLabel = 'Agencias De Corretaje';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name_corporative';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 10;

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return ['id', 'code', 'name_corporative', 'rif', 'ci_responsable', 'email', 'status', 'phone'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchTextColumns(): array
    {
        return ['name_corporative', 'email'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchCodeColumns(): array
    {
        return ['code', 'owner_code'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchDocumentColumns(): array
    {
        return ['rif', 'ci_responsable'];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Agency) {
            return [];
        }

        return [
            'Código' => filled($record->code) ? (string) $record->code : '—',
            'RIF' => filled($record->rif) ? (string) $record->rif : '—',
            'CI responsable' => filled($record->ci_responsable) ? (string) $record->ci_responsable : '—',
            'Estatus' => filled($record->status) ? (string) $record->status : '—',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return AgencyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AgencyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgenciesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'observationCommercialStructures' => fn ($query) => $query->orderByDesc('created_at'),
                'referidor:id,code,name_corporative',
                'referidorAgent:id,name,code_agent,status',
                'referredGeneralAgencies:id,referidor_id,code,name_corporative,status',
                'referredAgents' => fn ($query) => $query
                    ->select(['id', 'referidor_id', 'name', 'code_agent', 'agent_type_id', 'status'])
                    ->with('typeAgent:id,definition'),
            ]);
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
            'index' => ListAgencies::route('/'),
            'create' => CreateAgency::route('/create'),
            'view' => ViewAgency::route('/{record}'),
            'edit' => EditAgency::route('/{record}/edit'),
        ];
    }
}
