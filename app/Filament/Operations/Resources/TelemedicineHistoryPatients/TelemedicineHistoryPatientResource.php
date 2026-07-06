<?php

namespace App\Filament\Operations\Resources\TelemedicineHistoryPatients;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages\CreateTelemedicineHistoryPatient;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages\EditTelemedicineHistoryPatient;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages\ListTelemedicineHistoryPatients;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Pages\ViewTelemedicineHistoryPatient;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Schemas\TelemedicineHistoryPatientForm;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Schemas\TelemedicineHistoryPatientInfolist;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\Tables\TelemedicineHistoryPatientsTable;
use App\Models\Permission;
use App\Models\TelemedicineHistoryPatient;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TelemedicineHistoryPatientResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = TelemedicineHistoryPatient::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-i-exam-qualification';

    protected static ?string $navigationLabel = 'Historia Clínica';

    protected static string|UnitEnum|null $navigationGroup = 'TELEMEDICINA';

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'telemedicineDoctor',
                'telemedicinePatient',
            ]);
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

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'historias-clinicas-telemedicina')->first();

    //     // si es superadmin, retornar true
    //     if (in_array('SUPERADMIN', Auth::user()->departament)) {
    //         return true;
    //     }

    //     if (in_array($module, Auth::user()->departament)) {
    //         if (UserPermission::where('user_id', Auth::user()->id)->where('permission_id', $permission->id)->exists()) {
    //             return true;
    //         }
    //     }

    //     return false;
    // }
}
