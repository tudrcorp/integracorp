<?php

namespace App\Filament\Business\Resources\Affiliations;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use App\Models\Affiliation;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Business\Resources\Affiliations\Pages\EditAffiliation;
use App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation;
use App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Business\Resources\Affiliations\Pages\CreateAffiliation;
use App\Filament\Business\Resources\Affiliations\Schemas\AffiliationForm;
use App\Filament\Business\Resources\Affiliations\Tables\AffiliationsTable;
use App\Filament\Business\Resources\Affiliations\Schemas\AffiliationInfolist;
use App\Filament\Business\Resources\Affiliations\RelationManagers\AffiliatesRelationManager;
use App\Filament\Business\Resources\Affiliations\RelationManagers\PaidMembershipsRelationManager;

class AffiliationResource extends Resource
{
    protected static ?string $model = Affiliation::class;

    protected static ?string $navigationLabel = 'Individuales';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string | UnitEnum | null $navigationGroup = 'AFILIACIONES';

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
            AffiliatesRelationManager::class,
            PaidMembershipsRelationManager::class
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