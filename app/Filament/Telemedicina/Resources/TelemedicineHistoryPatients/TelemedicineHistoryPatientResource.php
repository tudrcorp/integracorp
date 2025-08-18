<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients;

use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages\CreateTelemedicineHistoryPatient;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages\EditTelemedicineHistoryPatient;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages\ListTelemedicineHistoryPatients;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Pages\ViewTelemedicineHistoryPatient;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Schemas\TelemedicineHistoryPatientForm;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Schemas\TelemedicineHistoryPatientInfolist;
use App\Filament\Telemedicina\Resources\TelemedicineHistoryPatients\Tables\TelemedicineHistoryPatientsTable;
use App\Models\TelemedicineHistoryPatient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TelemedicineHistoryPatientResource extends Resource
{
    protected static ?string $model = TelemedicineHistoryPatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-health-worker-form';

    protected static ?string $recordTitleAttribute = 'Historias';

    protected static ?string $navigationLabel = 'Historias Medicas';

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
            //
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