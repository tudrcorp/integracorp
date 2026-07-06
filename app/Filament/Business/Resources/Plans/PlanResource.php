<?php

namespace App\Filament\Business\Resources\Plans;

use App\Filament\Business\Resources\Plans\Pages\CreatePlan;
use App\Filament\Business\Resources\Plans\Pages\EditPlan;
use App\Filament\Business\Resources\Plans\Pages\ListPlans;
use App\Filament\Business\Resources\Plans\Pages\ViewPlan;
use App\Filament\Business\Resources\Plans\Schemas\PlanForm;
use App\Filament\Business\Resources\Plans\Schemas\PlanInfolist;
use App\Filament\Business\Resources\Plans\Tables\PlansTable;
use App\Models\Plan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationLabel = 'Planes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    public static function form(Schema $schema): Schema
    {
        return PlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlansTable::configure($table);
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
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'view' => ViewPlan::route('/{record}'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return in_array('SUPERADMIN', auth()->user()->departament ?? [], true);
    }
}
