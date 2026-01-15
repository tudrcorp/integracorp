<?php

namespace App\Filament\Administration\Resources\RrhhAsignacions;

use App\Filament\Administration\Resources\RrhhAsignacions\Pages\CreateRrhhAsignacion;
use App\Filament\Administration\Resources\RrhhAsignacions\Pages\EditRrhhAsignacion;
use App\Filament\Administration\Resources\RrhhAsignacions\Pages\ListRrhhAsignacions;
use App\Filament\Administration\Resources\RrhhAsignacions\Schemas\RrhhAsignacionForm;
use App\Filament\Administration\Resources\RrhhAsignacions\Tables\RrhhAsignacionsTable;
use App\Models\RrhhAsignacion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RrhhAsignacionResource extends Resource
{
    protected static ?string $model = RrhhAsignacion::class;

    protected static string | UnitEnum | null $navigationGroup = 'RRHH';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-plus-small';

    protected static ?string $navigationLabel = 'Asignaciones';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return RrhhAsignacionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RrhhAsignacionsTable::configure($table);
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
            'index' => ListRrhhAsignacions::route('/'),
            'create' => CreateRrhhAsignacion::route('/create'),
            'edit' => EditRrhhAsignacion::route('/{record}/edit'),
        ];
    }
}
