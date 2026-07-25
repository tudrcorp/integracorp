<?php

namespace App\Filament\Operations\Resources\OperationInventoryUbications;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\OperationInventoryUbications\Pages\CreateOperationInventoryUbication;
use App\Filament\Operations\Resources\OperationInventoryUbications\Pages\EditOperationInventoryUbication;
use App\Filament\Operations\Resources\OperationInventoryUbications\Pages\ListOperationInventoryUbications;
use App\Filament\Operations\Resources\OperationInventoryUbications\Pages\ViewOperationInventoryUbication;
use App\Filament\Operations\Resources\OperationInventoryUbications\Schemas\OperationInventoryUbicationForm;
use App\Filament\Operations\Resources\OperationInventoryUbications\Schemas\OperationInventoryUbicationInfolist;
use App\Filament\Operations\Resources\OperationInventoryUbications\Tables\OperationInventoryUbicationsTable;
use App\Models\OperationInventoryUbication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OperationInventoryUbicationResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = OperationInventoryUbication::class;

    protected static ?string $navigationLabel = 'Almacenes';

    protected static ?string $modelLabel = 'Almacén';

    protected static ?string $pluralModelLabel = 'Almacenes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|UnitEnum|null $navigationGroup = 'INVENTARIO DIAGNOMOVIL';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return OperationInventoryUbicationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationInventoryUbicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationInventoryUbicationsTable::configure($table);
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
            'index' => ListOperationInventoryUbications::route('/'),
            'create' => CreateOperationInventoryUbication::route('/create'),
            'view' => ViewOperationInventoryUbication::route('/{record}'),
            'edit' => EditOperationInventoryUbication::route('/{record}/edit'),
        ];
    }
}
