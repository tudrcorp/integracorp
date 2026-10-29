<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Upgrades;

use App\Filament\Business\Resources\Upgrades\Pages\CreateUpgrade;
use App\Filament\Business\Resources\Upgrades\Pages\EditUpgrade;
use App\Filament\Business\Resources\Upgrades\Pages\ListUpgrades;
use App\Filament\Business\Resources\Upgrades\Pages\ViewUpgrade;
use App\Filament\Business\Resources\Upgrades\Schemas\UpgradeForm;
use App\Filament\Business\Resources\Upgrades\Schemas\UpgradeInfolist;
use App\Filament\Business\Resources\Upgrades\Tables\UpgradesTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliateUpgrade;
use App\Models\Upgrade;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Catálogo de upgrades: servicios, coberturas y precio por rango de edad.
 * Lo que se asigna a afiliados sale de aquí; nada se borra, se inactiva.
 */
class UpgradeResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Upgrade::class;

    protected static ?string $modelLabel = 'upgrade';

    protected static ?string $pluralModelLabel = 'upgrades';

    protected static ?string $navigationLabel = 'Upgrades';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return UpgradeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UpgradeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UpgradesTable::configure($table);
    }

    /**
     * @return Builder<Upgrade>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['activeCoverages.activeAgeRanges'])
            ->withCount([
                'activeServices as active_services_count',
                'activeCoverages as active_coverages_count',
                'corporateAssignments as active_corporate_assignments_count' => fn (Builder $query): Builder => $query->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE),
                'individualAssignments as active_individual_assignments_count' => fn (Builder $query): Builder => $query->where('status', AffiliateUpgrade::STATUS_ACTIVE),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUpgrades::route('/'),
            'create' => CreateUpgrade::route('/create'),
            'view' => ViewUpgrade::route('/{record}'),
            'edit' => EditUpgrade::route('/{record}/edit'),
        ];
    }
}
