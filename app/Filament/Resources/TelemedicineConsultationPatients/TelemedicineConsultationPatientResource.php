<?php

namespace App\Filament\Resources\TelemedicineConsultationPatients;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use App\Models\TelemedicineConsultationPatient;
use App\Filament\Resources\TelemedicineConsultationPatients\Pages\EditTelemedicineConsultationPatient;
use App\Filament\Resources\TelemedicineConsultationPatients\Pages\ViewTelemedicineConsultationPatient;
use App\Filament\Resources\TelemedicineConsultationPatients\Pages\ListTelemedicineConsultationPatients;
use App\Filament\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;
use App\Filament\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientForm;
use App\Filament\Resources\TelemedicineConsultationPatients\Tables\TelemedicineConsultationPatientsTable;
use App\Filament\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientInfolist;
use App\Filament\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientLabsRelationManager;
use App\Filament\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientStudiesRelationManager;
use App\Filament\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientMedicationsRelationManager;
use App\Filament\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientSpecialistsRelationManager;

class TelemedicineConsultationPatientResource extends Resource
{
    protected static ?string $model = TelemedicineConsultationPatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-call-centre';

    protected static ?string $recordTitleAttribute = 'Historia';

    protected static string | UnitEnum | null $navigationGroup = 'TELEMEDICINA';

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