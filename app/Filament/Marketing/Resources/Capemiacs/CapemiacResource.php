<?php

namespace App\Filament\Marketing\Resources\Capemiacs;

use App\Filament\Marketing\Resources\Capemiacs\Pages\CreateCapemiac;
use App\Filament\Marketing\Resources\Capemiacs\Pages\EditCapemiac;
use App\Filament\Marketing\Resources\Capemiacs\Pages\ListCapemiacs;
use App\Filament\Marketing\Resources\Capemiacs\Pages\ViewCapemiac;
use App\Filament\Marketing\Resources\Capemiacs\Schemas\CapemiacForm;
use App\Filament\Marketing\Resources\Capemiacs\Schemas\CapemiacInfolist;
use App\Filament\Marketing\Resources\Capemiacs\Tables\CapemiacsTable;
use App\Models\Capemiac;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CapemiacResource extends Resource
{
    protected static ?string $model = Capemiac::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'CAPEMIAC';

    public static function form(Schema $schema): Schema
    {
        return CapemiacForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CapemiacInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CapemiacsTable::configure($table);
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
            'index' => ListCapemiacs::route('/'),
            'create' => CreateCapemiac::route('/create'),
            'view' => ViewCapemiac::route('/{record}'),
            'edit' => EditCapemiac::route('/{record}/edit'),
        ];
    }
}
