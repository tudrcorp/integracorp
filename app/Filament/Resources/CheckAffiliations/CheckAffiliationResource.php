<?php

namespace App\Filament\Resources\CheckAffiliations;

use App\Filament\Resources\CheckAffiliations\Pages\CreateCheckAffiliation;
use App\Filament\Resources\CheckAffiliations\Pages\EditCheckAffiliation;
use App\Filament\Resources\CheckAffiliations\Pages\ListCheckAffiliations;
use App\Filament\Resources\CheckAffiliations\Pages\ViewCheckAffiliation;
use App\Filament\Resources\CheckAffiliations\Schemas\CheckAffiliationForm;
use App\Filament\Resources\CheckAffiliations\Schemas\CheckAffiliationInfolist;
use App\Filament\Resources\CheckAffiliations\Tables\CheckAffiliationsTable;
use App\Models\CheckAffiliation;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CheckAffiliationResource extends Resource
{
    protected static ?string $model = CheckAffiliation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'HISTORICOS';

    protected static ?string $navigationLabel = 'Afiliaciones';

    public static function form(Schema $schema): Schema
    {
        return CheckAffiliationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CheckAffiliationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CheckAffiliationsTable::configure($table);
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
            'index' => ListCheckAffiliations::route('/'),
            'create' => CreateCheckAffiliation::route('/create'),
            'view' => ViewCheckAffiliation::route('/{record}'),
            'edit' => EditCheckAffiliation::route('/{record}/edit'),
        ];
    }
}