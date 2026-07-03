<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationRenovationHistories;

use App\Filament\Business\Resources\AffiliationRenovationHistories\Pages\ListAffiliationRenovationHistories;
use App\Filament\Business\Resources\AffiliationRenovationHistories\Pages\ViewAffiliationRenovationHistory;
use App\Filament\Business\Resources\AffiliationRenovationHistories\Schemas\AffiliationRenovationHistoryInfolist;
use App\Filament\Business\Resources\AffiliationRenovationHistories\Tables\AffiliationRenovationHistoriesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\AffiliationRenovationHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AffiliationRenovationHistoryResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = AffiliationRenovationHistory::class;

    protected static ?string $navigationLabel = 'Histórico de renovaciones';

    protected static ?string $modelLabel = 'histórico de renovación';

    protected static ?string $pluralModelLabel = 'histórico de renovaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationRenovationHistoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationRenovationHistoriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'affiliation.agency',
            'affiliation.agent',
            'affiliate',
            'plan',
            'previousPlan',
            'coverage',
            'ageRange',
        ]);
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

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliationRenovationHistories::route('/'),
            'view' => ViewAffiliationRenovationHistory::route('/{record}'),
        ];
    }
}
