<?php

namespace App\Filament\Resources\TelemedicineDoctors;

use App\Filament\Resources\TelemedicineDoctors\Pages\CreateTelemedicineDoctor;
use App\Filament\Resources\TelemedicineDoctors\Pages\EditTelemedicineDoctor;
use App\Filament\Resources\TelemedicineDoctors\Pages\ListTelemedicineDoctors;
use App\Filament\Resources\TelemedicineDoctors\Pages\ViewTelemedicineDoctor;
use App\Filament\Resources\TelemedicineDoctors\Schemas\TelemedicineDoctorForm;
use App\Filament\Resources\TelemedicineDoctors\Schemas\TelemedicineDoctorInfolist;
use App\Filament\Resources\TelemedicineDoctors\Tables\TelemedicineDoctorsTable;
use App\Models\TelemedicineDoctor;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelemedicineDoctorResource extends Resource
{
    protected static ?string $model = TelemedicineDoctor::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-doctor';

    protected static ?string $recordTitleAttribute = 'Doctor';

    protected static string | UnitEnum | null $navigationGroup = 'TELEMEDICINA';

    protected static ?string $navigationLabel = 'Doctores';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return TelemedicineDoctorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicineDoctorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicineDoctorsTable::configure($table);
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
            'index' => ListTelemedicineDoctors::route('/'),
            'create' => CreateTelemedicineDoctor::route('/create'),
            'view' => ViewTelemedicineDoctor::route('/{record}'),
            'edit' => EditTelemedicineDoctor::route('/{record}/edit'),
        ];
    }
}