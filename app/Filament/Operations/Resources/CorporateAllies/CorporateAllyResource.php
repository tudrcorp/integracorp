<?php

namespace App\Filament\Operations\Resources\CorporateAllies;

use App\Filament\Operations\Resources\CorporateAllies\Pages\CreateCorporateAlly;
use App\Filament\Operations\Resources\CorporateAllies\Pages\EditCorporateAlly;
use App\Filament\Operations\Resources\CorporateAllies\Pages\ListCorporateAllies;
use App\Filament\Operations\Resources\CorporateAllies\Pages\ViewCorporateAlly;
use App\Filament\Operations\Resources\CorporateAllies\Schemas\CorporateAllyForm;
use App\Filament\Operations\Resources\CorporateAllies\Schemas\CorporateAllyInfolist;
use App\Filament\Operations\Resources\CorporateAllies\Tables\CorporateAlliesTable;
use App\Models\CorporateAlly;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CorporateAllyResource extends Resource
{
    protected static ?string $model = CorporateAlly::class;

    protected static ?string $navigationLabel = 'Aliados corporativos';

    protected static ?string $pluralModelLabel = 'Aliados corporativos';

    protected static ?string $modelLabel = 'Aliado corporativo';

    protected static ?string $recordTitleAttribute = 'company_name';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return CorporateAllyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CorporateAllyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CorporateAlliesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['country', 'state', 'city', 'corporateAllyObservacions']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCorporateAllies::route('/'),
            'create' => CreateCorporateAlly::route('/create'),
            'view' => ViewCorporateAlly::route('/{record}'),
            'edit' => EditCorporateAlly::route('/{record}/edit'),
        ];
    }
}
