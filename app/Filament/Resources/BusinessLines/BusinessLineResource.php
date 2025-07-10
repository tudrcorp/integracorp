<?php

namespace App\Filament\Resources\BusinessLines;

use BackedEnum;
use Filament\Tables\Table;
use App\Models\BusinessLine;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\BusinessLines\Pages\EditBusinessLine;
use App\Filament\Resources\BusinessLines\Pages\ViewBusinessLine;
use App\Filament\Resources\BusinessLines\Pages\ListBusinessLines;
use App\Filament\Resources\BusinessLines\Pages\CreateBusinessLine;
use App\Filament\Resources\BusinessLines\Schemas\BusinessLineForm;
use App\Filament\Resources\BusinessLines\Tables\BusinessLinesTable;
use App\Filament\Resources\BusinessLines\Schemas\BusinessLineInfolist;
use App\Filament\Resources\BusinessLines\RelationManagers\PlansRelationManager;
use UnitEnum;

class BusinessLineResource extends Resource
{
    protected static ?string $model = BusinessLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowTrendingUp;

    protected static string | UnitEnum | null $navigationGroup = 'TDEC';

    protected static ?string $navigationLabel = 'Lineas de Servicios';

    public static function form(Schema $schema): Schema
    {
        return BusinessLineForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BusinessLineInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessLinesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // PlansRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessLines::route('/'),
            'create' => CreateBusinessLine::route('/create'),
            'view' => ViewBusinessLine::route('/{record}'),
            'edit' => EditBusinessLine::route('/{record}/edit'),
        ];
    }
}