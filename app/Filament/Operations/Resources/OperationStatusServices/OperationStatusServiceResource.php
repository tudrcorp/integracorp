<?php

namespace App\Filament\Operations\Resources\OperationStatusServices;

use App\Filament\Operations\Resources\OperationStatusServices\Pages\CreateOperationStatusService;
use App\Filament\Operations\Resources\OperationStatusServices\Pages\EditOperationStatusService;
use App\Filament\Operations\Resources\OperationStatusServices\Pages\ListOperationStatusServices;
use App\Filament\Operations\Resources\OperationStatusServices\Pages\ViewOperationStatusService;
use App\Filament\Operations\Resources\OperationStatusServices\Schemas\OperationStatusServiceForm;
use App\Filament\Operations\Resources\OperationStatusServices\Schemas\OperationStatusServiceInfolist;
use App\Filament\Operations\Resources\OperationStatusServices\Tables\OperationStatusServicesTable;
use App\Models\OperationStatusService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OperationStatusServiceResource extends Resource
{
    protected static ?string $model = OperationStatusService::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Estados de Servicio';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

    public static function form(Schema $schema): Schema
    {
        return OperationStatusServiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationStatusServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationStatusServicesTable::configure($table);
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
            'index' => ListOperationStatusServices::route('/'),
            'create' => CreateOperationStatusService::route('/create'),
            'view' => ViewOperationStatusService::route('/{record}'),
            'edit' => EditOperationStatusService::route('/{record}/edit'),
        ];
    }
}
