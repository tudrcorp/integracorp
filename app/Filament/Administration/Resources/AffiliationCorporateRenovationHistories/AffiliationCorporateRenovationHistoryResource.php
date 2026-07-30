<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories;

use App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\Pages\ListAffiliationCorporateRenovationHistories;
use App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\Pages\ViewAffiliationCorporateRenovationHistory;
use App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\Schemas\AffiliationCorporateRenovationHistoryInfolist;
use App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\Tables\AffiliationCorporateRenovationHistoriesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\AffiliationCorporateRenovationHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AffiliationCorporateRenovationHistoryResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = AffiliationCorporateRenovationHistory::class;

    protected static ?string $navigationLabel = 'Histórico renovaciones corporativas';

    protected static ?string $modelLabel = 'histórico de renovación corporativa';

    protected static ?string $pluralModelLabel = 'histórico de renovaciones corporativas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?int $navigationSort = 5;

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationCorporateRenovationHistoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationCorporateRenovationHistoriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'affiliationCorporate.agency',
            'affiliationCorporate.agent',
            'affiliateCorporate',
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
            'index' => ListAffiliationCorporateRenovationHistories::route('/'),
            'view' => ViewAffiliationCorporateRenovationHistory::route('/{record}'),
        ];
    }
}
