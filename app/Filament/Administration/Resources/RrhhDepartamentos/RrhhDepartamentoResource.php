<?php

namespace App\Filament\Administration\Resources\RrhhDepartamentos;

use App\Filament\Administration\Resources\RrhhDepartamentos\Pages\CreateRrhhDepartamento;
use App\Filament\Administration\Resources\RrhhDepartamentos\Pages\EditRrhhDepartamento;
use App\Filament\Administration\Resources\RrhhDepartamentos\Pages\ListRrhhDepartamentos;
use App\Filament\Administration\Resources\RrhhDepartamentos\Schemas\RrhhDepartamentoForm;
use App\Filament\Administration\Resources\RrhhDepartamentos\Tables\RrhhDepartamentosTable;
use App\Models\RrhhDepartamento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RrhhDepartamentoResource extends Resource
{
    protected static ?string $model = RrhhDepartamento::class;

    protected static string | UnitEnum | null $navigationGroup = 'RRHH';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationLabel = 'Departamentos';

    protected static ?int $navigationSort = 1;
    public static function form(Schema $schema): Schema
    {
        return RrhhDepartamentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RrhhDepartamentosTable::configure($table);
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
            'index' => ListRrhhDepartamentos::route('/'),
            'create' => CreateRrhhDepartamento::route('/create'),
            'edit' => EditRrhhDepartamento::route('/{record}/edit'),
        ];
    }
}
