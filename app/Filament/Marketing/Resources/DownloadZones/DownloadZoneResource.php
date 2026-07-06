<?php

namespace App\Filament\Marketing\Resources\DownloadZones;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Marketing\Resources\DownloadZones\Pages\CreateDownloadZone;
use App\Filament\Marketing\Resources\DownloadZones\Pages\EditDownloadZone;
use App\Filament\Marketing\Resources\DownloadZones\Pages\ListDownloadZones;
use App\Filament\Marketing\Resources\DownloadZones\Schemas\DownloadZoneForm;
use App\Filament\Marketing\Resources\DownloadZones\Tables\DownloadZonesTable;
use App\Models\DownloadZone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DownloadZoneResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = DownloadZone::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Documentos';

    protected static string|UnitEnum|null $navigationGroup = 'ZONA DE DESCARGA';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return DownloadZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DownloadZonesTable::configure($table);
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
            'index' => ListDownloadZones::route('/'),
            'create' => CreateDownloadZone::route('/create'),
            'edit' => EditDownloadZone::route('/{record}/edit'),
        ];
    }
}
