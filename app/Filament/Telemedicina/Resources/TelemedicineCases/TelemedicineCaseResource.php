<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases;

use App\Filament\Telemedicina\Resources\TelemedicineCases\Pages\CreateTelemedicineCase;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Pages\EditTelemedicineCase;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Pages\ListTelemedicineCases;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Pages\ViewTelemedicineCase;
use App\Filament\Telemedicina\Resources\TelemedicineCases\RelationManagers\ConsultationsRelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Schemas\TelemedicineCaseForm;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Schemas\TelemedicineCaseInfolist;
use App\Filament\Telemedicina\Resources\TelemedicineCases\Tables\TelemedicineCasesTable;
use App\Models\TelemedicineCase;
use App\Support\Telemedicine\TelemedicineCaseGlobalSearch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

class TelemedicineCaseResource extends Resource
{
    protected static ?string $model = TelemedicineCase::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-call-centre';

    protected static string|UnitEnum|null $navigationGroup = 'GESTIÓN TELEMÉDICA';

    protected static ?string $pluralLabel = 'Gestión de casos de telemedicina';

    protected static ?string $navigationLabel = 'Casos de Telemedicina';

    protected static ?int $navigationSort = 3;

    protected static int $globalSearchResultsLimit = TelemedicineCaseGlobalSearch::RESULTS_LIMIT;

    /**
     * Filament solo habilita la búsqueda global si hay atributos declarados; la
     * búsqueda real (código, cédula, nombre y diagnóstico) la resuelve
     * {@see TelemedicineCaseGlobalSearch}.
     *
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'patient_name'];
    }

    /**
     * @return Collection<int, \Filament\GlobalSearch\GlobalSearchResult>
     */
    public static function getGlobalSearchResults(string $search): Collection
    {
        return TelemedicineCaseGlobalSearch::results($search);
    }

    public static function form(Schema $schema): Schema
    {
        return TelemedicineCaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicineCaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicineCasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ConsultationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineCases::route('/'),
            'create' => CreateTelemedicineCase::route('/create'),
            'view' => ViewTelemedicineCase::route('/{record}'),
            'edit' => EditTelemedicineCase::route('/{record}/edit'),
        ];
    }
}
