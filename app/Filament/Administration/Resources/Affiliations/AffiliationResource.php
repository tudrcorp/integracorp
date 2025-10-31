<?php

namespace App\Filament\Administration\Resources\Affiliations;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use App\Models\Affiliation;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Filament\Administration\Resources\Affiliations\Pages\EditAffiliation;
use App\Filament\Administration\Resources\Affiliations\Pages\ListAffiliations;
use App\Filament\Administration\Resources\Affiliations\Pages\CreateAffiliation;
use App\Filament\Administration\Resources\Affiliations\Schemas\AffiliationForm;
use App\Filament\Administration\Resources\Affiliations\Tables\AffiliationsTable;
use App\Filament\Administration\Resources\Affiliations\RelationManagers\AffiliatesRelationManager;
use App\Filament\Administration\Resources\Affiliations\RelationManagers\PaidMembershipsRelationManager;

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
            'edit' => EditAffiliation::route('/{record}/edit'),
        ];
    }
}
