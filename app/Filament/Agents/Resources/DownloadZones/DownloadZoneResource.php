<?php

namespace App\Filament\Agents\Resources\DownloadZones;

use App\Filament\Agents\Resources\DownloadZones\Pages\CreateDownloadZone;
use App\Filament\Agents\Resources\DownloadZones\Pages\EditDownloadZone;
use App\Filament\Agents\Resources\DownloadZones\Pages\ListDownloadZones;
use App\Filament\Agents\Resources\DownloadZones\Pages\ViewDownloadZone;
use App\Filament\Agents\Resources\DownloadZones\Schemas\DownloadZoneForm;
use App\Filament\Agents\Resources\DownloadZones\Schemas\DownloadZoneInfolist;
use App\Filament\Agents\Resources\DownloadZones\Tables\DownloadZonesTable;
use App\Models\DownloadZone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DownloadZoneResource extends Resource
{
    protected static ?string $model = DownloadZone::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Documentos';

    protected static string | UnitEnum | null $navigationGroup = 'ZONA DE DESCARGA';

    public static function form(Schema $schema): Schema
    {
        return DownloadZoneForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DownloadZoneInfolist::configure($schema);
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
            'view' => ViewDownloadZone::route('/{record}'),
            'edit' => EditDownloadZone::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     // Deshabilitado temporalmente por mantenimiento
    //     return false;
    // }
}