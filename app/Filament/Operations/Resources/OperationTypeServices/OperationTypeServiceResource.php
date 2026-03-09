<?php

namespace App\Filament\Operations\Resources\OperationTypeServices;

use App\Filament\Operations\Resources\OperationTypeServices\Pages\CreateOperationTypeService;
use App\Filament\Operations\Resources\OperationTypeServices\Pages\EditOperationTypeService;
use App\Filament\Operations\Resources\OperationTypeServices\Pages\ListOperationTypeServices;
use App\Filament\Operations\Resources\OperationTypeServices\Pages\ViewOperationTypeService;
use App\Filament\Operations\Resources\OperationTypeServices\Schemas\OperationTypeServiceForm;
use App\Filament\Operations\Resources\OperationTypeServices\Schemas\OperationTypeServiceInfolist;
use App\Filament\Operations\Resources\OperationTypeServices\Tables\OperationTypeServicesTable;
use App\Models\OperationTypeService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OperationTypeServiceResource extends Resource
{
    protected static ?string $model = OperationTypeService::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Tipos de Servicios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    public static function form(Schema $schema): Schema
    {
        return OperationTypeServiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationTypeServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationTypeServicesTable::configure($table);
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
            'index' => ListOperationTypeServices::route('/'),
            'create' => CreateOperationTypeService::route('/create'),
            'view' => ViewOperationTypeService::route('/{record}'),
            'edit' => EditOperationTypeService::route('/{record}/edit'),
        ];
    }
}
