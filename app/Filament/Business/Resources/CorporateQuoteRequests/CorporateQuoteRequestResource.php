<?php

namespace App\Filament\Business\Resources\CorporateQuoteRequests;

use App\Filament\Business\Resources\CorporateQuoteRequests\Pages\CreateCorporateQuoteRequest;
use App\Filament\Business\Resources\CorporateQuoteRequests\Pages\EditCorporateQuoteRequest;
use App\Filament\Business\Resources\CorporateQuoteRequests\Pages\ListCorporateQuoteRequests;
use App\Filament\Business\Resources\CorporateQuoteRequests\Pages\ViewCorporateQuoteRequest;
use App\Filament\Business\Resources\CorporateQuoteRequests\Schemas\CorporateQuoteRequestForm;
use App\Filament\Business\Resources\CorporateQuoteRequests\Schemas\CorporateQuoteRequestInfolist;
use App\Filament\Business\Resources\CorporateQuoteRequests\Tables\CorporateQuoteRequestsTable;
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

    protected static ?string $navigationLabel = 'Dress Taylor';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-link';

    protected static string | UnitEnum | null $navigationGroup = 'SOLICITUDES';

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