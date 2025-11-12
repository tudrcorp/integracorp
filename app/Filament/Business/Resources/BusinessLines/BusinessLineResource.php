<?php

namespace App\Filament\Business\Resources\BusinessLines;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use App\Models\BusinessLine;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use App\Filament\Business\Resources\BusinessLines\Pages\EditBusinessLine;
use App\Filament\Business\Resources\BusinessLines\Pages\ViewBusinessLine;
use App\Filament\Business\Resources\BusinessLines\Pages\ListBusinessLines;
use App\Filament\Business\Resources\BusinessLines\Pages\CreateBusinessLine;
use App\Filament\Business\Resources\BusinessLines\Schemas\BusinessLineForm;
use App\Filament\Business\Resources\BusinessLines\Tables\BusinessLinesTable;
use App\Filament\Business\Resources\BusinessLines\Schemas\BusinessLineInfolist;

class BusinessLineResource extends Resource
{
    protected static ?string $model = BusinessLine::class;

    protected static ?string $navigationLabel = 'Lineas de Servicio';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-equals';

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

    public static function shouldRegisterNavigation(): bool
    {
        //Solo el Administrador General del Modulo de Business puede acceder a este recurso
        if (Auth::user()->is_business_admin) {
            return true;
        }
        return false;
    }
}