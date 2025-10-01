<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Models\TelemedicineConsultationPatient;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\EditTelemedicineConsultationPatient;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\ViewTelemedicineConsultationPatient;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\ListTelemedicineConsultationPatients;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientForm;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Tables\TelemedicineConsultationPatientsTable;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientInfolist;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicineFollowUpsRelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientLabsRelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientStudiesRelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientMedicationsRelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientSpecialistsRelationManager;

class TelemedicineConsultationPatientResource extends Resource
{
    protected static ?string $model = TelemedicineConsultationPatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-desktop-app';

    protected static ?string $pluralLabel = 'Gestión de Servicio';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return TelemedicineConsultationPatientForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicineConsultationPatientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicineConsultationPatientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TelemedicinePatientMedicationsRelationManager::class,
            TelemedicinePatientLabsRelationManager::class,
            TelemedicinePatientStudiesRelationManager::class,
            TelemedicinePatientSpecialistsRelationManager::class,
            
            
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineConsultationPatients::route('/'),
            'create' => CreateTelemedicineConsultationPatient::route('/create'),
            'view' => ViewTelemedicineConsultationPatient::route('/{record}'),
            'edit' => EditTelemedicineConsultationPatient::route('/{record}/edit'),
        ];
    }
}