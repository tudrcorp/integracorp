<?php

namespace App\Filament\Marketing\Resources\RrhhColaboradors;

use App\Filament\Marketing\Resources\RrhhColaboradors\Pages\CreateRrhhColaborador;
use App\Filament\Marketing\Resources\RrhhColaboradors\Pages\EditRrhhColaborador;
use App\Filament\Marketing\Resources\RrhhColaboradors\Pages\ListRrhhColaboradors;
use App\Filament\Marketing\Resources\RrhhColaboradors\Pages\ViewRrhhColaborador;
use App\Filament\Marketing\Resources\RrhhColaboradors\Schemas\RrhhColaboradorForm;
use App\Filament\Marketing\Resources\RrhhColaboradors\Schemas\RrhhColaboradorInfolist;
use App\Filament\Marketing\Resources\RrhhColaboradors\Tables\RrhhColaboradorsTable;
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

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string | UnitEnum | null $navigationGroup = 'ADMINISTRACION/RRHH';

    protected static ?string $navigationLabel = 'Colaboradores';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return RrhhColaboradorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RrhhColaboradorInfolist::configure($schema);
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
            'view' => ViewRrhhColaborador::route('/{record}'),
            'edit' => EditRrhhColaborador::route('/{record}/edit'),
        ];
    }
}
