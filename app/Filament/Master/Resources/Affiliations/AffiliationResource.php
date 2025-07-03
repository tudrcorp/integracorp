<?php

namespace App\Filament\Master\Resources\Affiliations;

use App\Filament\Master\Resources\Affiliations\Pages\CreateAffiliation;
use App\Filament\Master\Resources\Affiliations\Pages\EditAffiliation;
use App\Filament\Master\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Master\Resources\Affiliations\Pages\ViewAffiliation;
use App\Filament\Master\Resources\Affiliations\Schemas\AffiliationForm;
use App\Filament\Master\Resources\Affiliations\Schemas\AffiliationInfolist;
use App\Filament\Master\Resources\Affiliations\Tables\AffiliationsTable;
use App\Models\Affiliation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliationResource extends Resource
{
    protected static ?string $model = Affiliation::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-user';

    protected static string | UnitEnum | null $navigationGroup = 'AFILIACIONES';

    protected static ?string $navigationLabel = 'Individuales';

    public static function form(Schema $schema): Schema
    {
        return AffiliationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationsTable::configure($table);
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
            'index' => ListAffiliations::route('/'),
            'create' => CreateAffiliation::route('/create'),
            'view' => ViewAffiliation::route('/{record}'),
            'edit' => EditAffiliation::route('/{record}/edit'),
        ];
    }
}