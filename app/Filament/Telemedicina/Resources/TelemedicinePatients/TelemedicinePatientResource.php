<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients;

use App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages\CreateTelemedicinePatient;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages\EditTelemedicinePatient;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages\ListTelemedicinePatients;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages\ViewTelemedicinePatient;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\Schemas\TelemedicinePatientForm;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\Schemas\TelemedicinePatientInfolist;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\Tables\TelemedicinePatientsTable;
use App\Models\TelemedicinePatient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TelemedicinePatientResource extends Resource
{
    protected static ?string $model = TelemedicinePatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-virus-patient';

    protected static ?string $recordTitleAttribute = 'Pacientes';

    protected static ?string $pluralLabel = 'Pacientes';

    public static function form(Schema $schema): Schema
    {
        return TelemedicinePatientForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicinePatientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicinePatientsTable::configure($table);
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
            'index' => ListTelemedicinePatients::route('/'),
            'create' => CreateTelemedicinePatient::route('/create'),
            'view' => ViewTelemedicinePatient::route('/{record}'),
            'edit' => EditTelemedicinePatient::route('/{record}/edit'),
        ];
    }
}