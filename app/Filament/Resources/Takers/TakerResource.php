<?php

namespace App\Filament\Resources\Takers;

use App\Filament\Resources\Takers\Pages\CreateTaker;
use App\Filament\Resources\Takers\Pages\EditTaker;
use App\Filament\Resources\Takers\Pages\ListTakers;
use App\Filament\Resources\Takers\Pages\ViewTaker;
use App\Filament\Resources\Takers\Schemas\TakerForm;
use App\Filament\Resources\Takers\Schemas\TakerInfolist;
use App\Filament\Resources\Takers\Tables\TakersTable;
use App\Models\Taker;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TakerResource extends Resource
{
    protected static ?string $model = Taker::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::HandThumbUp;

    protected static ?string $navigationLabel = 'TOMADORES';

    public static function form(Schema $schema): Schema
    {
        return TakerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TakerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TakersTable::configure($table);
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
            'index' => ListTakers::route('/'),
            'create' => CreateTaker::route('/create'),
            'view' => ViewTaker::route('/{record}'),
            'edit' => EditTaker::route('/{record}/edit'),
        ];
    }
}