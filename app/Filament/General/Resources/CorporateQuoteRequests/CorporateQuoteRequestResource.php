<?php

namespace App\Filament\General\Resources\CorporateQuoteRequests;

use App\Filament\General\Resources\CorporateQuoteRequests\Pages\CreateCorporateQuoteRequest;
use App\Filament\General\Resources\CorporateQuoteRequests\Pages\EditCorporateQuoteRequest;
use App\Filament\General\Resources\CorporateQuoteRequests\Pages\ListCorporateQuoteRequests;
use App\Filament\General\Resources\CorporateQuoteRequests\Pages\ViewCorporateQuoteRequest;
use App\Filament\General\Resources\CorporateQuoteRequests\Schemas\CorporateQuoteRequestForm;
use App\Filament\General\Resources\CorporateQuoteRequests\Schemas\CorporateQuoteRequestInfolist;
use App\Filament\General\Resources\CorporateQuoteRequests\Tables\CorporateQuoteRequestsTable;
use App\Models\CorporateQuoteRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CorporateQuoteRequestResource extends Resource
{
    protected static ?string $model = CorporateQuoteRequest::class;


    protected static string | UnitEnum | null $navigationGroup = 'Cotizaciones';

    protected static ?string $navigationLabel = 'Solicitudes';

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
            //
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