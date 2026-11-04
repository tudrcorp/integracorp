<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CapitationPlans;

use App\Filament\Business\Resources\CapitationPlans\Pages\CreateCapitationPlan;
use App\Filament\Business\Resources\CapitationPlans\Pages\EditCapitationPlan;
use App\Filament\Business\Resources\CapitationPlans\Pages\ListCapitationPlans;
use App\Filament\Business\Resources\CapitationPlans\Pages\ViewCapitationPlan;
use App\Filament\Business\Resources\CapitationPlans\Schemas\CapitationPlanForm;
use App\Filament\Business\Resources\CapitationPlans\Schemas\CapitationPlanInfolist;
use App\Filament\Business\Resources\CapitationPlans\Tables\CapitationPlansTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\CapitationPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Planes de capitado de los proveedores de la red Outsourcing (costo por afiliado).
 */
class CapitationPlanResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = CapitationPlan::class;

    protected static ?string $modelLabel = 'plan de capitado';

    protected static ?string $pluralModelLabel = 'planes de capitado';

    protected static ?string $navigationLabel = 'Planes de Capitado';

    protected static ?string $slug = 'planes-de-capitado';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return CapitationPlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CapitationPlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CapitationPlansTable::configure($table);
    }

    /**
     * @return Builder<CapitationPlan>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier:id,name,rif,deleted_at', 'doctorNurse:id,name,rif,deleted_at'])
            ->withCount('benefits');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCapitationPlans::route('/'),
            'create' => CreateCapitationPlan::route('/create'),
            'view' => ViewCapitationPlan::route('/{record}'),
            'edit' => EditCapitationPlan::route('/{record}/edit'),
        ];
    }
}
