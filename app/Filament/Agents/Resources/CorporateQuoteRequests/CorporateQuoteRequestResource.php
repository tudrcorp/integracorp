<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Models\CorporateQuoteRequest;
use App\Filament\Agents\Resources\CorporateQuoteRequests\Pages\EditCorporateQuoteRequest;
use App\Filament\Agents\Resources\CorporateQuoteRequests\Pages\ViewCorporateQuoteRequest;
use App\Filament\Agents\Resources\CorporateQuoteRequests\Pages\ListCorporateQuoteRequests;
use App\Filament\Agents\Resources\CorporateQuoteRequests\Pages\CreateCorporateQuoteRequest;
use App\Filament\Agents\Resources\CorporateQuoteRequests\Schemas\CorporateQuoteRequestForm;
use App\Filament\Agents\Resources\CorporateQuoteRequests\Tables\CorporateQuoteRequestsTable;
use App\Filament\Agents\Resources\CorporateQuoteRequests\Schemas\CorporateQuoteRequestInfolist;
use App\Filament\Agents\Resources\CorporateQuoteRequests\RelationManagers\DetailsRelationManager;
use App\Filament\Agents\Resources\CorporateQuoteRequests\RelationManagers\DetailsDataRelationManager;

class CorporateQuoteRequestResource extends Resource
{
    protected static ?string $model = CorporateQuoteRequest::class;

    protected static string | UnitEnum | null $navigationGroup = 'CORPORATIVAS';

    protected static ?string $navigationLabel = 'Cotización a la Medida';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return CorporateQuoteRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CorporateQuoteRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CorporateQuoteRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DetailsRelationManager::class,
            DetailsDataRelationManager::class

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCorporateQuoteRequests::route('/'),
            'create' => CreateCorporateQuoteRequest::route('/create'),
            'view' => ViewCorporateQuoteRequest::route('/{record}'),
            'edit' => EditCorporateQuoteRequest::route('/{record}/edit'),
        ];
    }
}