<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\RenovationCorporates;

use App\Filament\Administration\Resources\RenovationCorporates\Pages\ListRenovationCorporates;
use App\Filament\Administration\Resources\RenovationCorporates\Pages\ViewRenovationCorporate;
use App\Filament\Administration\Resources\RenovationCorporates\Schemas\RenovationCorporateInfolist;
use App\Filament\Administration\Resources\RenovationCorporates\Tables\RenovationsCorporateTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\RenovationCorporate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RenovationCorporateResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = RenovationCorporate::class;

    protected static ?string $navigationLabel = 'Renovaciones Corporativas';

    protected static ?string $modelLabel = 'renovación';

    protected static ?string $pluralModelLabel = 'renovaciones corporativas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?int $navigationSort = 4;

    public static function infolist(Schema $schema): Schema
    {
        return RenovationCorporateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RenovationsCorporateTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'affiliationCorporate.agency',
            'affiliationCorporate.agent',
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
            'index' => ListRenovationCorporates::route('/'),
            'view' => ViewRenovationCorporate::route('/{record}'),
        ];
    }
}
