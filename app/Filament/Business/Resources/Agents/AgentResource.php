<?php

namespace App\Filament\Business\Resources\Agents;

use App\Filament\Business\Resources\Agents\Pages\CreateAgent;
use App\Filament\Business\Resources\Agents\Pages\EditAgent;
use App\Filament\Business\Resources\Agents\Pages\ListAgents;
use App\Filament\Business\Resources\Agents\Pages\ViewAgent;
use App\Filament\Business\Resources\Agents\Schemas\AgentForm;
use App\Filament\Business\Resources\Agents\Schemas\AgentInfolist;
use App\Filament\Business\Resources\Agents\Tables\AgentsTable;
use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\Agent;
use App\Support\Filament\BusinessGlobalSearch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AgentResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = Agent::class;

    protected static ?string $navigationLabel = 'Agentes De Corretaje';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 20;

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return ['id', 'code_agent', 'name', 'ci', 'rif', 'email', 'status', 'phone', 'owner_code'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchTextColumns(): array
    {
        return ['name', 'email'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchCodeColumns(): array
    {
        return ['code_agent', 'owner_code'];
    }

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchDocumentColumns(): array
    {
        return ['ci', 'rif'];
    }

    protected static function businessGlobalSearchExtraConstraints(Builder $query, string $term): void
    {
        $agentId = BusinessGlobalSearch::extractAgentDisplayCodeId($term);

        if ($agentId !== null) {
            $query->orWhere('agents.id', $agentId);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Agent) {
            return [];
        }

        return [
            'Código' => 'AGT-000'.$record->id,
            'CI' => filled($record->ci) ? (string) $record->ci : '—',
            'RIF' => filled($record->rif) ? (string) $record->rif : '—',
            'Estatus' => filled($record->status) ? (string) $record->status : '—',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|\Illuminate\Contracts\Support\Htmlable
    {
        if (! $record instanceof Agent) {
            return parent::getGlobalSearchResultTitle($record);
        }

        $name = filled($record->name) ? (string) $record->name : 'Agente';

        return $name.' · AGT-000'.$record->id;
    }

    public static function form(Schema $schema): Schema
    {
        return AgentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AgentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'observationCommercialStructures' => fn ($query) => $query->orderByDesc('created_at'),
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
            'index' => ListAgents::route('/'),
            'create' => CreateAgent::route('/create'),
            'view' => ViewAgent::route('/{record}'),
            'edit' => EditAgent::route('/{record}/edit'),
        ];
    }
}
