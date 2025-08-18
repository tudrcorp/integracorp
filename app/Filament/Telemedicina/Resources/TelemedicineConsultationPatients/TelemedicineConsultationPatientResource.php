<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients;

use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\EditTelemedicineConsultationPatient;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\ListTelemedicineConsultationPatients;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\ViewTelemedicineConsultationPatient;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientForm;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientInfolist;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Tables\TelemedicineConsultationPatientsTable;
use App\Models\TelemedicineConsultationPatient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TelemedicineConsultationPatientResource extends Resource
{
    protected static ?string $model = TelemedicineConsultationPatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-call-centre';

    protected static ?string $navigationLabel = 'Consultas';

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