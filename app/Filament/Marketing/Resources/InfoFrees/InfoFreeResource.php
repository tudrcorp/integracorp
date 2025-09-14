<?php

namespace App\Filament\Marketing\Resources\InfoFrees;

use App\Filament\Marketing\Resources\InfoFrees\Pages\CreateInfoFree;
use App\Filament\Marketing\Resources\InfoFrees\Pages\EditInfoFree;
use App\Filament\Marketing\Resources\InfoFrees\Pages\ListInfoFrees;
use App\Filament\Marketing\Resources\InfoFrees\Pages\ViewInfoFree;
use App\Filament\Marketing\Resources\InfoFrees\Schemas\InfoFreeForm;
use App\Filament\Marketing\Resources\InfoFrees\Schemas\InfoFreeInfolist;
use App\Filament\Marketing\Resources\InfoFrees\Tables\InfoFreesTable;
use App\Models\InfoFree;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InfoFreeResource extends Resource
{
    protected static ?string $model = InfoFree::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-c-circle-stack';

    protected static ?string $navigationLabel = 'Data Externa(FREE)';

    public static function form(Schema $schema): Schema
    {
        return InfoFreeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InfoFreeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InfoFreesTable::configure($table);
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
            'index' => ListInfoFrees::route('/'),
            'create' => CreateInfoFree::route('/create'),
            'view' => ViewInfoFree::route('/{record}'),
            'edit' => EditInfoFree::route('/{record}/edit'),
        ];
    }
}