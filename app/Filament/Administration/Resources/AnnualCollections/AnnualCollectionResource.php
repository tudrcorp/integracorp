<?php

namespace App\Filament\Administration\Resources\AnnualCollections;

use App\Filament\Administration\Resources\AnnualCollections\Pages\CreateAnnualCollection;
use App\Filament\Administration\Resources\AnnualCollections\Pages\EditAnnualCollection;
use App\Filament\Administration\Resources\AnnualCollections\Pages\ListAnnualCollections;
use App\Filament\Administration\Resources\AnnualCollections\Pages\ViewAnnualCollection;
use App\Filament\Administration\Resources\AnnualCollections\Schemas\AnnualCollectionForm;
use App\Filament\Administration\Resources\AnnualCollections\Schemas\AnnualCollectionInfolist;
use App\Filament\Administration\Resources\AnnualCollections\Tables\AnnualCollectionsTable;
use App\Models\AnnualCollection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class AnnualCollectionResource extends Resource
{
    protected static ?string $model = AnnualCollection::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?string $navigationLabel = 'Cobranza Por Mes';

    public static function form(Schema $schema): Schema
    {
        return AnnualCollectionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AnnualCollectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnnualCollectionsTable::configure($table);
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
            'index' => ListAnnualCollections::route('/'),
            'create' => CreateAnnualCollection::route('/create'),
            'view' => ViewAnnualCollection::route('/{record}'),
            'edit' => EditAnnualCollection::route('/{record}/edit'),
        ];
    }
}
