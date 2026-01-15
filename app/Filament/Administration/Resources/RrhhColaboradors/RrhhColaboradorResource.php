<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors;

use App\Filament\Administration\Resources\RrhhColaboradors\Pages\CreateRrhhColaborador;
use App\Filament\Administration\Resources\RrhhColaboradors\Pages\EditRrhhColaborador;
use App\Filament\Administration\Resources\RrhhColaboradors\Pages\ListRrhhColaboradors;
use App\Filament\Administration\Resources\RrhhColaboradors\Schemas\RrhhColaboradorForm;
use App\Filament\Administration\Resources\RrhhColaboradors\Tables\RrhhColaboradorsTable;
use App\Models\RrhhColaborador;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RrhhColaboradorResource extends Resource
{
    protected static ?string $model = RrhhColaborador::class;

    protected static string | UnitEnum | null $navigationGroup = 'RRHH';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Colaboradores';

    public static function form(Schema $schema): Schema
    {
        return RrhhColaboradorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RrhhColaboradorsTable::configure($table);
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
            'index' => ListRrhhColaboradors::route('/'),
            'create' => CreateRrhhColaborador::route('/create'),
            'edit' => EditRrhhColaborador::route('/{record}/edit'),
        ];
    }
}
