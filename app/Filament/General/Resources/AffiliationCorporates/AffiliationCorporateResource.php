<?php

namespace App\Filament\General\Resources\AffiliationCorporates;

use App\Filament\General\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate;
use App\Filament\General\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate;
use App\Filament\General\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\General\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\General\Resources\AffiliationCorporates\Schemas\AffiliationCorporateForm;
use App\Filament\General\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use App\Filament\General\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;
use App\Models\AffiliationCorporate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliationCorporateResource extends Resource
{
    protected static ?string $model = AffiliationCorporate::class;

    protected static string | UnitEnum | null $navigationGroup = 'Afiliaciones';

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
            //
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