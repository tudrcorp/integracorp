<?php

namespace App\Filament\Business\Resources\BusinessLines;

use App\Filament\Business\Resources\BusinessLines\Pages\CreateBusinessLine;
use App\Filament\Business\Resources\BusinessLines\Pages\EditBusinessLine;
use App\Filament\Business\Resources\BusinessLines\Pages\ListBusinessLines;
use App\Filament\Business\Resources\BusinessLines\Pages\ViewBusinessLine;
use App\Filament\Business\Resources\BusinessLines\Schemas\BusinessLineForm;
use App\Filament\Business\Resources\BusinessLines\Schemas\BusinessLineInfolist;
use App\Filament\Business\Resources\BusinessLines\Tables\BusinessLinesTable;
use App\Models\BusinessLine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BusinessLineResource extends Resource
{
    protected static ?string $model = BusinessLine::class;

    protected static ?string $navigationLabel = 'Lineas de Servicio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'CONFIGURACIÓN';

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
            //
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