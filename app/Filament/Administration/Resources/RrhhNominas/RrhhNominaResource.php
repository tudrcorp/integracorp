<?php

namespace App\Filament\Administration\Resources\RrhhNominas;

use App\Filament\Administration\Resources\RrhhNominas\Pages\CreateRrhhNomina;
use App\Filament\Administration\Resources\RrhhNominas\Pages\EditRrhhNomina;
use App\Filament\Administration\Resources\RrhhNominas\Pages\ListRrhhNominas;
use App\Filament\Administration\Resources\RrhhNominas\Schemas\RrhhNominaForm;
use App\Filament\Administration\Resources\RrhhNominas\Tables\RrhhNominasTable;
use App\Models\RrhhNomina;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RrhhNominaResource extends Resource
{
    protected static ?string $model = RrhhNomina::class;

    protected static string | UnitEnum | null $navigationGroup = 'NOMINA';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Cálculos de Nomina';

    public static function form(Schema $schema): Schema
    {
        return RrhhNominaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RrhhNominasTable::configure($table);
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
            'index' => ListRrhhNominas::route('/'),
            'create' => CreateRrhhNomina::route('/create'),
            'edit' => EditRrhhNomina::route('/{record}/edit'),
        ];
    }
}
