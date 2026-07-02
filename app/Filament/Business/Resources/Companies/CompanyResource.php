<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Companies;

use App\Filament\Business\Clusters\NuevosNegocios\NuevosNegociosCluster;
use App\Filament\Business\Resources\Companies\Pages\CreateCompany;
use App\Filament\Business\Resources\Companies\Pages\EditCompany;
use App\Filament\Business\Resources\Companies\Pages\ListCompanies;
use App\Filament\Business\Resources\Companies\Pages\ViewCompany;
use App\Filament\Business\Resources\Companies\Schemas\CompanyForm;
use App\Filament\Business\Resources\Companies\Schemas\CompanyInfolist;
use App\Filament\Business\Resources\Companies\Tables\CompaniesTable;
use App\Models\Company;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $cluster = NuevosNegociosCluster::class;

    protected static ?string $navigationLabel = 'Empresas';

    protected static ?string $modelLabel = 'nuevo negocio';

    protected static ?string $pluralModelLabel = 'nuevos negocios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CompanyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompanyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompaniesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'planGenerator',
                'responsibles' => fn ($query) => $query
                    ->withCount('associates')
                    ->with([
                        'state',
                        'zone',
                        'associates' => fn ($query) => $query->orderByDesc('registered_at'),
                    ]),
            ])
            ->withCount('responsibles')
            ->withSum('responsibles', 'contracted_days')
            ->withCount('associates');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'view' => ViewCompany::route('/{record}'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
