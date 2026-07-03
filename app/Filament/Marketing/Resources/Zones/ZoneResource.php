<?php

namespace App\Filament\Marketing\Resources\Zones;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Marketing\Resources\Zones\Pages\CreateZone;
use App\Filament\Marketing\Resources\Zones\Pages\EditZone;
use App\Filament\Marketing\Resources\Zones\Pages\ListZones;
use App\Filament\Marketing\Resources\Zones\Schemas\ZoneForm;
use App\Filament\Marketing\Resources\Zones\Tables\ZonesTable;
use App\Models\Zone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ZoneResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Zone::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Gestión de Carpetas';

    protected static string|UnitEnum|null $navigationGroup = 'ZONA DE DESCARGA';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZonesTable::configure($table);
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
            'index' => ListZones::route('/'),
            'create' => CreateZone::route('/create'),
            'edit' => EditZone::route('/{record}/edit'),
        ];
    }
}
