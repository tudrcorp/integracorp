<?php

namespace App\Filament\Resources\DownloadZones;

use App\Filament\Resources\DownloadZones\Pages\CreateDownloadZone;
use App\Filament\Resources\DownloadZones\Pages\EditDownloadZone;
use App\Filament\Resources\DownloadZones\Pages\ListDownloadZones;
use App\Filament\Resources\DownloadZones\Pages\ViewDownloadZone;
use App\Filament\Resources\DownloadZones\Schemas\DownloadZoneForm;
use App\Filament\Resources\DownloadZones\Schemas\DownloadZoneInfolist;
use App\Filament\Resources\DownloadZones\Tables\DownloadZonesTable;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ArrowDownTray;

    protected static string | UnitEnum | null $navigationGroup = 'TDEC';

    protected static ?string $navigationLabel = 'Zona de Descarga';

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
}