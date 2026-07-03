<?php

namespace App\Filament\Administration\Resources\RrhhCargos;

use App\Filament\Administration\Resources\RrhhCargos\Pages\CreateRrhhCargo;
use App\Filament\Administration\Resources\RrhhCargos\Pages\EditRrhhCargo;
use App\Filament\Administration\Resources\RrhhCargos\Pages\ListRrhhCargos;
use App\Filament\Administration\Resources\RrhhCargos\Schemas\RrhhCargoForm;
use App\Filament\Administration\Resources\RrhhCargos\Tables\RrhhCargosTable;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\RrhhCargo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RrhhCargoResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = RrhhCargo::class;

    protected static string|UnitEnum|null $navigationGroup = 'RRHH';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document';

    protected static ?string $navigationLabel = 'Cargos';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return RrhhCargoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RrhhCargosTable::configure($table);
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
            'index' => ListRrhhCargos::route('/'),
            'create' => CreateRrhhCargo::route('/create'),
            'edit' => EditRrhhCargo::route('/{record}/edit'),
        ];
    }
}
