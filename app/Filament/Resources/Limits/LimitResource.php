<?php

namespace App\Filament\Resources\Limits;

use BackedEnum;
use App\Models\Limit;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\Limits\Pages\EditLimit;
use App\Filament\Resources\Limits\Pages\ViewLimit;
use App\Filament\Resources\Limits\Pages\ListLimits;
use App\Filament\Resources\Limits\Pages\CreateLimit;
use App\Filament\Resources\Limits\Schemas\LimitForm;
use App\Filament\Resources\Limits\Tables\LimitsTable;
use App\Filament\Resources\Limits\Schemas\LimitInfolist;
use App\Filament\Resources\Limits\RelationManagers\BenefitsRelationManager;

class LimitResource extends Resource
{
    protected static ?string $model = Limit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::AdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'LÍMITES';

    public static function form(Schema $schema): Schema
    {
        return LimitForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LimitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LimitsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BenefitsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLimits::route('/'),
            'create' => CreateLimit::route('/create'),
            'view' => ViewLimit::route('/{record}'),
            'edit' => EditLimit::route('/{record}/edit'),
        ];
    }
}