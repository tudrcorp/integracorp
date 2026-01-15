<?php

namespace App\Filament\Administration\Resources\RrhhDeduccions;

use App\Filament\Administration\Resources\RrhhDeduccions\Pages\CreateRrhhDeduccion;
use App\Filament\Administration\Resources\RrhhDeduccions\Pages\EditRrhhDeduccion;
use App\Filament\Administration\Resources\RrhhDeduccions\Pages\ListRrhhDeduccions;
use App\Filament\Administration\Resources\RrhhDeduccions\Schemas\RrhhDeduccionForm;
use App\Filament\Administration\Resources\RrhhDeduccions\Tables\RrhhDeduccionsTable;
use App\Models\RrhhDeduccion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RrhhDeduccionResource extends Resource
{
    protected static ?string $model = RrhhDeduccion::class;

    protected static string | UnitEnum  | null $navigationGroup = 'RRHH';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-minus';

    protected static ?string $navigationLabel = 'Deducciones';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return RrhhDeduccionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RrhhDeduccionsTable::configure($table);
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
            'index' => ListRrhhDeduccions::route('/'),
            'create' => CreateRrhhDeduccion::route('/create'),
            'edit' => EditRrhhDeduccion::route('/{record}/edit'),
        ];
    }
}
