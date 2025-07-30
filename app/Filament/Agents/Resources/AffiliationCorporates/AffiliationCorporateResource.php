<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use App\Models\AffiliationCorporate;
use Filament\Support\Icons\Heroicon;
use App\Filament\Agents\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate;
use App\Filament\Agents\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Agents\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Agents\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate;
use App\Filament\Agents\Resources\AffiliationCorporates\Schemas\AffiliationCorporateForm;
use App\Filament\Agents\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;
use App\Filament\Agents\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use App\Filament\Agents\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager;
use App\Filament\Agents\Resources\AffiliationCorporates\RelationManagers\PaidMembershipCorporatesRelationManager;

class AffiliationCorporateResource extends Resource
{
    protected static ?string $model = AffiliationCorporate::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-user-group';

    protected static ?string $navigationLabel = 'Consultar Afiliación';

    protected static string | UnitEnum | null $navigationGroup = 'CORPORATIVAS';

    protected static ?int $navigationSort = 2;

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