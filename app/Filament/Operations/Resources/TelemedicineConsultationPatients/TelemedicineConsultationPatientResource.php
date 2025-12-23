<?php

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Models\TelemedicineConsultationPatient;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages\EditTelemedicineConsultationPatient;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages\ViewTelemedicineConsultationPatient;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages\ListTelemedicineConsultationPatients;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientForm;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Tables\TelemedicineConsultationPatientsTable;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientInfolist;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientLabsRelationManager;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientStudiesRelationManager;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientMedicationsRelationManager;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientSpecialistsRelationManager;

class TelemedicineConsultationPatientResource extends Resource
{
    protected static ?string $model = TelemedicineConsultationPatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-call-centre';

    // protected static ?string $recordTitleAttribute = 'Historias Clínicas';

    // protected static string | UnitEnum | null $navigationGroup = 'TELEMEDICINA';

    protected static ?string $navigationLabel = 'Consultas Telemédicas';

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
            TelemedicinePatientLabsRelationManager::class,
            TelemedicinePatientMedicationsRelationManager::class,
            TelemedicinePatientStudiesRelationManager::class,
            TelemedicinePatientSpecialistsRelationManager::class,
            
            
            //
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