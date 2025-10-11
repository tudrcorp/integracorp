<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Models\TelemedicineHistoryPatient;
use Filament\Pages\Enums\SubNavigationPosition;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages\EditTelemedicineHistoryPatient;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages\ViewTelemedicineHistoryPatient;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages\ListTelemedicineHistoryPatients;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages\CreateTelemedicineHistoryPatient;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Schemas\TelemedicineHistoryPatientForm;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Tables\TelemedicineHistoryPatientsTable;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Schemas\TelemedicineHistoryPatientInfolist;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\RelationManagers\FamilyHistoriesRelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\RelationManagers\SurgicalHistoriesRelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\RelationManagers\PathologicalHistoriesRelationManager;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\RelationManagers\GynecologicalHistoriesRelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\RelationManagers\NoPathologicalHistoriesRelationManager;

class TelemedicineHistoryPatientResource extends Resource
{
    protected static ?string $model = TelemedicineHistoryPatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-health-worker-form';

    protected static ?string $navigationLabel = 'Historias Medicas';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return TelemedicineHistoryPatientForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicineHistoryPatientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicineHistoryPatientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PathologicalHistoriesRelationManager::class,
            NoPathologicalHistoriesRelationManager::class,
            FamilyHistoriesRelationManager::class,
            SurgicalHistoriesRelationManager::class,
            GynecologicalHistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineHistoryPatients::route('/'),
            'create' => CreateTelemedicineHistoryPatient::route('/create'),
            'view' => ViewTelemedicineHistoryPatient::route('/{record}'),
            'edit' => EditTelemedicineHistoryPatient::route('/{record}/edit'),
        ];
    }
}