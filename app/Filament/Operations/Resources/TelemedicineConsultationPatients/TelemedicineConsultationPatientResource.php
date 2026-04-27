<?php

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients;

use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages\EditTelemedicineConsultationPatient;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages\ListTelemedicineConsultationPatients;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Pages\ViewTelemedicineConsultationPatient;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientLabsRelationManager;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientMedicationsRelationManager;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientSpecialistsRelationManager;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\RelationManagers\TelemedicinePatientStudiesRelationManager;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientForm;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Schemas\TelemedicineConsultationPatientInfolist;
use App\Filament\Operations\Resources\TelemedicineConsultationPatients\Tables\TelemedicineConsultationPatientsTable;
use App\Models\Permission;
use App\Models\TelemedicineConsultationPatient;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'telemedicinePatient',
                'telemedicineDoctor',
                'telemedicineCase',
                'telemedicineServiceList',
                'telemedicinePriority',
            ]);
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

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'consultas-telemedicas')->first();

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
