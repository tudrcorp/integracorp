<?php

namespace App\Filament\Administration\Resources\AffiliationCorporates;

use App\Filament\Administration\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate;
use App\Filament\Administration\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate;
use App\Filament\Administration\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Administration\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Administration\Resources\AffiliationCorporates\RelationManagers\AffiliationCorporatePlansRelationManager;
use App\Filament\Administration\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager;
use App\Filament\Administration\Resources\AffiliationCorporates\RelationManagers\PaidMembershipCorporatesRelationManager;
use App\Filament\Administration\Resources\AffiliationCorporates\Schemas\AffiliationCorporateForm;
use App\Filament\Administration\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use App\Filament\Administration\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;
use App\Models\AffiliationCorporate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class AffiliationCorporateResource extends Resource
{
    protected static ?string $model = AffiliationCorporate::class;

    protected static ?string $navigationLabel = 'Corporativas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'AFILIACIONES';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return AffiliationCorporateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationCorporateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationCorporatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AffiliationCorporatePlansRelationManager::class,
            CorporateAffiliatesRelationManager::class,
            PaidMembershipCorporatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliationCorporates::route('/'),
            'create' => CreateAffiliationCorporate::route('/create'),
            'view' => ViewAffiliationCorporate::route('/{record}'),
            'edit' => EditAffiliationCorporate::route('/{record}/edit'),
        ];
    }
}
