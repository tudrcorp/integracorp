<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices;

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\CreateOperationCoordinationService;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\EditOperationCoordinationService;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ListOperationCoordinationServices;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ViewOperationCoordinationService;
use App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers\TelemedicinePatientLabsRelationManager;
use App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers\TelemedicinePatientMedicationsRelationManager;
use App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers\TelemedicinePatientSpecialtiesRelationManager;
use App\Filament\Operations\Resources\OperationCoordinationServices\RelationManagers\TelemedicinePatientStudiesRelationManager;
use App\Filament\Operations\Resources\OperationCoordinationServices\Schemas\OperationCoordinationServiceForm;
use App\Filament\Operations\Resources\OperationCoordinationServices\Schemas\OperationCoordinationServiceInfolist;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Models\OperationCoordinationService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class OperationCoordinationServiceResource extends Resource
{
    protected static ?string $model = OperationCoordinationService::class;

    protected static ?string $navigationLabel = 'Lista de Servicios';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    public static function getNavigationBadge(): ?string
    {
        return OperationCoordinationService::whereDate('created_at', Carbon::today())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return OperationCoordinationService::whereDate('created_at', Carbon::today())->count() > 0 ? 'success' : 'gray';
    }

    public static function form(Schema $schema): Schema
    {
        return OperationCoordinationServiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationCoordinationServiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationCoordinationServicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
            TelemedicinePatientMedicationsRelationManager::class,
            TelemedicinePatientLabsRelationManager::class,
            TelemedicinePatientStudiesRelationManager::class,
            TelemedicinePatientSpecialtiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperationCoordinationServices::route('/'),
            'create' => CreateOperationCoordinationService::route('/create'),
            'view' => ViewOperationCoordinationService::route('/{record}'),
            'edit' => EditOperationCoordinationService::route('/{record}/edit'),
        ];
    }
}
