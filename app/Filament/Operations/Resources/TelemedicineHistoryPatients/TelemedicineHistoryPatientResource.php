<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients;

use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages\CreateTelemedicineHistoryPatient;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages\EditTelemedicineHistoryPatient;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages\ListTelemedicineHistoryPatients;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages\ViewTelemedicineHistoryPatient;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Schemas\TelemedicineHistoryPatientForm;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Schemas\TelemedicineHistoryPatientInfolist;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Tables\TelemedicineHistoryPatientsTable;
use App\Models\TelemedicineHistoryPatient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TelemedicineHistoryPatientResource extends Resource
{
    protected static ?string $model = TelemedicineHistoryPatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-i-exam-qualification';

    protected static ?string $navigationLabel = 'Historia Clínica';

    protected static ?string $title = 'Historia Clínica';

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
