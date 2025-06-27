<?php

namespace App\Filament\Resources\AffiliationCorporates;

use App\Filament\Resources\AffiliationCorporates\Pages\CreateAffiliationCorporate;
use App\Filament\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate;
use App\Filament\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Resources\AffiliationCorporates\Schemas\AffiliationCorporateForm;
use App\Filament\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist;
use App\Filament\Resources\AffiliationCorporates\Tables\AffiliationCorporatesTable;
use App\Models\AffiliationCorporate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AffiliationCorporateResource extends Resource
{
    protected static ?string $model = AffiliationCorporate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-rectangle-stack';

    protected static ?string $navigationLabel = 'CORPORATIVA';

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