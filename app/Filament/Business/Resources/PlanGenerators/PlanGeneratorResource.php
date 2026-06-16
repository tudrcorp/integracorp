<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators;

use App\Filament\Business\Resources\PlanGenerators\Pages\CreatePlanGenerator;
use App\Filament\Business\Resources\PlanGenerators\Pages\EditPlanGenerator;
use App\Filament\Business\Resources\PlanGenerators\Pages\ListPlanGenerators;
use App\Filament\Business\Resources\PlanGenerators\Pages\ViewPlanGenerator;
use App\Filament\Business\Resources\PlanGenerators\Schemas\PlanGeneratorForm;
use App\Filament\Business\Resources\PlanGenerators\Schemas\PlanGeneratorInfolist;
use App\Filament\Business\Resources\PlanGenerators\Tables\PlanGeneratorsTable;
use App\Models\PlanGenerator;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class PlanGeneratorResource extends Resource
{
    protected static ?string $model = PlanGenerator::class;

    protected static ?string $navigationLabel = 'Generador de Planes';

    protected static ?string $modelLabel = 'plan generado';

    protected static ?string $pluralModelLabel = 'planes generados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return PlanGeneratorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlanGeneratorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanGeneratorsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['columns', 'rows', 'rateRows']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanGenerators::route('/'),
            'create' => CreatePlanGenerator::route('/create'),
            'view' => ViewPlanGenerator::route('/{record}'),
            'edit' => EditPlanGenerator::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::userCanAccessPlanGenerators();
    }

    public static function canAccess(): bool
    {
        return self::userCanAccessPlanGenerators();
    }

    public static function canViewAny(): bool
    {
        return self::userCanAccessPlanGenerators();
    }

    public static function canCreate(): bool
    {
        return self::userCanAccessPlanGenerators();
    }

    public static function canView(Model $record): bool
    {
        return self::userCanAccessPlanGenerators();
    }

    public static function canEdit(Model $record): bool
    {
        return self::userCanAccessPlanGenerators();
    }

    public static function canDelete(Model $record): bool
    {
        return self::userCanAccessPlanGenerators();
    }

    private static function userCanAccessPlanGenerators(): bool
    {
        $departments = (array) (Auth::user()?->departament ?? []);

        return in_array('SUPERADMIN', $departments, true);
    }
}
