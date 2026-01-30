<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates;

use App\Filament\Operations\Resources\AffiliateCorporates\Pages\CreateAffiliateCorporate;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\EditAffiliateCorporate;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\ListAffiliateCorporates;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\ViewAffiliateCorporate;
use App\Filament\Operations\Resources\AffiliateCorporates\Schemas\AffiliateCorporateForm;
use App\Filament\Operations\Resources\AffiliateCorporates\Schemas\AffiliateCorporateInfolist;
use App\Filament\Operations\Resources\AffiliateCorporates\Tables\AffiliateCorporatesTable;
use App\Models\AffiliateCorporate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AffiliateCorporateResource extends Resource
{
    protected static ?string $model = AffiliateCorporate::class;

    protected static ?string $navigationLabel = 'Corporativos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string | UnitEnum | null $navigationGroup = 'AFILIADOS';

    public static function form(Schema $schema): Schema
    {
        return AffiliateCorporateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateCorporateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateCorporatesTable::configure($table);
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
            'index' => ListAffiliateCorporates::route('/'),
            'create' => CreateAffiliateCorporate::route('/create'),
            'view' => ViewAffiliateCorporate::route('/{record}'),
            'edit' => EditAffiliateCorporate::route('/{record}/edit'),
        ];
    }
}
