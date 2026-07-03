<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates;

use App\Filament\Business\Clusters\NuevosNegocios\NuevosNegociosCluster;
use App\Filament\Business\Resources\CompanyAssociates\Pages\ListCompanyAssociates;
use App\Filament\Business\Resources\CompanyAssociates\Pages\ViewCompanyAssociate;
use App\Filament\Business\Resources\CompanyAssociates\Schemas\CompanyAssociateInfolist;
use App\Filament\Business\Resources\CompanyAssociates\Tables\CompanyAssociatesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\CompanyAssociate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CompanyAssociateResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = CompanyAssociate::class;

    protected static ?string $cluster = NuevosNegociosCluster::class;

    protected static ?string $navigationLabel = 'Asociados';

    protected static ?string $modelLabel = 'asociado';

    protected static ?string $pluralModelLabel = 'asociados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function infolist(Schema $schema): Schema
    {
        return CompanyAssociateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanyAssociatesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'company',
                'responsible',
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanyAssociates::route('/'),
            'view' => ViewCompanyAssociate::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
