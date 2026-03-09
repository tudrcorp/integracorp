<?php

namespace App\Filament\Operations\Resources\OperationTypeNegotiations;

use App\Filament\Operations\Resources\OperationTypeNegotiations\Pages\CreateOperationTypeNegotiation;
use App\Filament\Operations\Resources\OperationTypeNegotiations\Pages\EditOperationTypeNegotiation;
use App\Filament\Operations\Resources\OperationTypeNegotiations\Pages\ListOperationTypeNegotiations;
use App\Filament\Operations\Resources\OperationTypeNegotiations\Pages\ViewOperationTypeNegotiation;
use App\Filament\Operations\Resources\OperationTypeNegotiations\Schemas\OperationTypeNegotiationForm;
use App\Filament\Operations\Resources\OperationTypeNegotiations\Schemas\OperationTypeNegotiationInfolist;
use App\Filament\Operations\Resources\OperationTypeNegotiations\Tables\OperationTypeNegotiationsTable;
use App\Models\OperationTypeNegotiation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OperationTypeNegotiationResource extends Resource
{
    protected static ?string $model = OperationTypeNegotiation::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Tipos de Negociación';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    public static function form(Schema $schema): Schema
    {
        return OperationTypeNegotiationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationTypeNegotiationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationTypeNegotiationsTable::configure($table);
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
            'index' => ListOperationTypeNegotiations::route('/'),
            'create' => CreateOperationTypeNegotiation::route('/create'),
            'view' => ViewOperationTypeNegotiation::route('/{record}'),
            'edit' => EditOperationTypeNegotiation::route('/{record}/edit'),
        ];
    }
}
