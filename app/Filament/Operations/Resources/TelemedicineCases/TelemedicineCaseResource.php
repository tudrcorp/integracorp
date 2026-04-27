<?php

namespace App\Filament\Operations\Resources\TelemedicineCases;

use App\Filament\Operations\Resources\TelemedicineCases\Pages\CreateTelemedicineCase;
use App\Filament\Operations\Resources\TelemedicineCases\Pages\EditTelemedicineCase;
use App\Filament\Operations\Resources\TelemedicineCases\Pages\ListTelemedicineCases;
use App\Filament\Operations\Resources\TelemedicineCases\Pages\ViewTelemedicineCase;
use App\Filament\Operations\Resources\TelemedicineCases\RelationManagers\ConsultationsRelationManager;
use App\Filament\Operations\Resources\TelemedicineCases\RelationManagers\ObservationsRelationManager;
use App\Filament\Operations\Resources\TelemedicineCases\RelationManagers\TelemedicineDocumentsRelationManager;
use App\Filament\Operations\Resources\TelemedicineCases\Schemas\TelemedicineCaseForm;
use App\Filament\Operations\Resources\TelemedicineCases\Schemas\TelemedicineCaseInfolist;
use App\Filament\Operations\Resources\TelemedicineCases\Tables\TelemedicineCasesTable;
use App\Models\Permission;
use App\Models\TelemedicineCase;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TelemedicineCaseResource extends Resource
{
    protected static ?string $model = TelemedicineCase::class;

    protected static string|UnitEnum|null $navigationGroup = 'TELEMEDICINA';

    protected static ?string $navigationLabel = 'Gestión de Casos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function form(Schema $schema): Schema
    {
        return TelemedicineCaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicineCaseInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'telemedicinePatient',
                'telemedicineDoctor',
                'priority',
                'city',
                'state',
                'country',
            ]);
    }

    public static function table(Table $table): Table
    {
        return TelemedicineCasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'consultations' => ConsultationsRelationManager::class,
            'observations' => ObservationsRelationManager::class,
            'documents' => TelemedicineDocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelemedicineCases::route('/'),
            'create' => CreateTelemedicineCase::route('/create'),
            'view' => ViewTelemedicineCase::route('/{record}'),
            'edit' => EditTelemedicineCase::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'casos-de-telemedicina')->first();

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
