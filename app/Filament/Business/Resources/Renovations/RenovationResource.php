<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Renovations;

use App\Filament\Business\Resources\Renovations\Pages\ListRenovations;
use App\Filament\Business\Resources\Renovations\Pages\ViewRenovation;
use App\Filament\Business\Resources\Renovations\Schemas\RenovationInfolist;
use App\Filament\Business\Resources\Renovations\Tables\RenovationsTable;
use App\Models\Renovation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RenovationResource extends Resource
{
    protected static ?string $model = Renovation::class;

    protected static ?string $navigationLabel = 'Renovaciones Individuales';

    protected static ?string $modelLabel = 'renovación';

    protected static ?string $pluralModelLabel = 'renovaciones individuales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return RenovationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RenovationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'affiliation.agency',
            'affiliation.agent',
            'affiliation.plan',
            'affiliation.coverage',
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
            'index' => ListRenovations::route('/'),
            'view' => ViewRenovation::route('/{record}'),
        ];
    }
}
