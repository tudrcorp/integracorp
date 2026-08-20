<?php

namespace App\Filament\Business\Resources\WhiteCompanies;

use App\Filament\Business\Resources\Concerns\ConfiguresBusinessGlobalSearch;
use App\Filament\Business\Resources\WhiteCompanies\Pages\CreateWhiteCompany;
use App\Filament\Business\Resources\WhiteCompanies\Pages\EditWhiteCompany;
use App\Filament\Business\Resources\WhiteCompanies\Pages\ListWhiteCompanies;
use App\Filament\Business\Resources\WhiteCompanies\RelationManagers\AssignedPlansRelationManager;
use App\Filament\Business\Resources\WhiteCompanies\RelationManagers\NegotiatedFeesRelationManager;
use App\Filament\Business\Resources\WhiteCompanies\Schemas\WhiteCompanyForm;
use App\Filament\Business\Resources\WhiteCompanies\Tables\WhiteCompaniesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\WhiteCompany;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WhiteCompanyResource extends Resource
{
    use AuthorizesDepartmentNavigation;
    use ConfiguresBusinessGlobalSearch;

    protected static ?string $model = WhiteCompany::class;

    protected static ?string $navigationLabel = 'Empresas Aliadas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|UnitEnum|null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 8;

    protected static ?int $globalSearchSort = 90;

    /**
     * @return list<string>
     */
    protected static function businessGlobalSearchSelectColumns(): array
    {
        return ['id', 'name', 'rif', 'email', 'phone'];
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
        return [];
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
        if (! $record instanceof WhiteCompany) {
            return [];
        }

        return [
            'RIF' => filled($record->rif) ? (string) $record->rif : '—',
            'Email' => filled($record->email) ? (string) $record->email : '—',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return WhiteCompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhiteCompaniesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Primero se asigna el plan, después se le pactan las netas.
            AssignedPlansRelationManager::class,
            NegotiatedFeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhiteCompanies::route('/'),
            'create' => CreateWhiteCompany::route('/create'),
            'edit' => EditWhiteCompany::route('/{record}/edit'),
        ];
    }

    // public static function shouldRegisterNavigation(): bool
    // {
    //     //Solo el Administrador General del Modulo de Business puede acceder a este recurso
    //     if (in_array('SUPERADMIN', auth()->user()->departament)) {
    //         return true;
    //     }
    //     return false;
    // }
}
