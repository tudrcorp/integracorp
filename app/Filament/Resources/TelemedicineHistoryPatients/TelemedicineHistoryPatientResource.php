<?php

namespace App\Filament\Resources\TelemedicineHistoryPatients;

use App\Filament\Resources\TelemedicineHistoryPatients\Pages\CreateTelemedicineHistoryPatient;
use App\Filament\Resources\TelemedicineHistoryPatients\Pages\EditTelemedicineHistoryPatient;
use App\Filament\Resources\TelemedicineHistoryPatients\Pages\ListTelemedicineHistoryPatients;
use App\Filament\Resources\TelemedicineHistoryPatients\Pages\ViewTelemedicineHistoryPatient;
use App\Filament\Resources\TelemedicineHistoryPatients\Schemas\TelemedicineHistoryPatientForm;
use App\Filament\Resources\TelemedicineHistoryPatients\Schemas\TelemedicineHistoryPatientInfolist;
use App\Filament\Resources\TelemedicineHistoryPatients\Tables\TelemedicineHistoryPatientsTable;
use App\Models\TelemedicineHistoryPatient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelemedicineHistoryPatientResource extends Resource
{
    protected static ?string $model = TelemedicineHistoryPatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-i-exam-multiple-choice';

    protected static ?string $recordTitleAttribute = 'Historia';

    protected static string | UnitEnum | null $navigationGroup = 'TELEMEDICINA';

    protected static ?string $navigationLabel = 'Historias Clínicas';

    protected static ?int $navigationSort = 3;

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