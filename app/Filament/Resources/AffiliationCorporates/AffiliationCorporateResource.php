<?php

namespace App\Filament\Resources\AffiliationCorporates;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use App\Models\AffiliationCorporate;
use Filament\Support\Icons\Heroicon;
use App\Filament\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate;
use App\Filament\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate;
use App\Filament\Resources\AffiliationCorporates\Schemas\AffiliationCorporateForm;
use App\Filament\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;
use App\Filament\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use App\Filament\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager;
use App\Filament\Resources\AffiliationCorporates\RelationManagers\PaidMembershipCorporatesRelationManager;
use App\Filament\Resources\AffiliationCorporates\RelationManagers\AffiliationCorporatePlansRelationManager;
use App\Filament\Resources\AffiliationCorporates\RelationManagers\StatusLogCorporateAffiliationsRelationManager;

class AffiliationCorporateResource extends Resource
{
    protected static ?string $model = AffiliationCorporate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-rectangle-stack';

    protected static string | UnitEnum | null $navigationGroup = 'AFILIACIONES';

    protected static ?string $navigationLabel = 'Corporativas';

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
            StatusLogCorporateAffiliationsRelationManager::class
            
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