<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\IndicadoresDeDesempeno;

use App\Filament\Operations\Resources\IndicadoresDeDesempeno\Pages\ListIndicadoresDeDesempeno;
use App\Models\HelpDesk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class IndicadoresDeDesempenoResource extends Resource
{
    protected static ?string $model = HelpDesk::class;

    protected static ?string $navigationLabel = 'Indicadores de desempeño';

    protected static ?string $pluralModelLabel = 'Indicadores de desempeño';

    protected static ?string $modelLabel = 'Indicador de desempeño';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'indicadores-de-desempeno';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndicadoresDeDesempeno::route('/'),
        ];
    }
}
