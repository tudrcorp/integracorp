<?php

namespace App\Filament\Administration\Resources\RrhhPrestamos;

use App\Filament\Administration\Resources\RrhhPrestamos\Pages\CreateRrhhPrestamo;
use App\Filament\Administration\Resources\RrhhPrestamos\Pages\EditRrhhPrestamo;
use App\Filament\Administration\Resources\RrhhPrestamos\Pages\ListRrhhPrestamos;
use App\Filament\Administration\Resources\RrhhPrestamos\Schemas\RrhhPrestamoForm;
use App\Filament\Administration\Resources\RrhhPrestamos\Tables\RrhhPrestamosTable;
use App\Models\RrhhPrestamo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RrhhPrestamoResource extends Resource
{
    protected static ?string $model = RrhhPrestamo::class;

    protected static string | UnitEnum | null $navigationGroup = 'RRHH';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-c-arrows-right-left';

    protected static ?string $navigationLabel = 'Prestamos';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return RrhhPrestamoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RrhhPrestamosTable::configure($table);
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
            'index' => ListRrhhPrestamos::route('/'),
            'create' => CreateRrhhPrestamo::route('/create'),
            'edit' => EditRrhhPrestamo::route('/{record}/edit'),
        ];
    }
}
