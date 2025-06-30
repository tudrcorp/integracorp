<?php

namespace App\Filament\Master\Resources\DownloadZones;

use App\Filament\Master\Resources\DownloadZones\Pages\CreateDownloadZone;
use App\Filament\Master\Resources\DownloadZones\Pages\EditDownloadZone;
use App\Filament\Master\Resources\DownloadZones\Pages\ListDownloadZones;
use App\Filament\Master\Resources\DownloadZones\Pages\ViewDownloadZone;
use App\Filament\Master\Resources\DownloadZones\Schemas\DownloadZoneForm;
use App\Filament\Master\Resources\DownloadZones\Schemas\DownloadZoneInfolist;
use App\Filament\Master\Resources\DownloadZones\Tables\DownloadZonesTable;
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

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-c-arrow-down-on-square-stack';

    protected static ?string $navigationLabel = 'ZONA DE DESCARGAS';

    protected static string | UnitEnum | null $navigationGroup = 'Organización';

    protected static ?int $navigationSort = 5;

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