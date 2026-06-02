<?php

namespace App\Filament\Operations\Resources\DoctorNurses;

use App\Filament\Operations\Resources\DoctorNurses\Pages\CreateDoctorNurse;
use App\Filament\Operations\Resources\DoctorNurses\Pages\EditDoctorNurse;
use App\Filament\Operations\Resources\DoctorNurses\Pages\ListDoctorNurses;
use App\Filament\Operations\Resources\DoctorNurses\Pages\ViewDoctorNurse;
use App\Filament\Operations\Resources\DoctorNurses\RelationManagers\DoctorNurseObservacionsRelationManager;
use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseForm;
use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseInfolist;
use App\Filament\Operations\Resources\DoctorNurses\Tables\DoctorNursesTable;
use App\Models\DoctorNurse;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class DoctorNurseResource extends Resource
{
    protected static ?string $model = DoctorNurse::class;

    protected static ?string $navigationLabel = 'Proveedores Naturales';

    protected static ?string $pluralModelLabel = 'Proveedores naturales';

    protected static ?string $modelLabel = 'Proveedor natural';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 12;

    protected static ?int $globalSearchSort = 20;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'razon_social',
            'rif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof DoctorNurse) {
            return [];
        }

        $details = [
            'RIF' => filled($record->rif) ? (string) $record->rif : '—',
        ];

        if (filled($record->razon_social)) {
            $details['Razón social'] = (string) $record->razon_social;
        }

        if (filled($record->correo_principal)) {
            $details['Correo'] = (string) $record->correo_principal;
        }

        if (filled($record->personal_phone) || filled($record->local_phone)) {
            $details['Teléfono'] = (string) ($record->personal_phone ?: $record->local_phone);
        }

        if (filled($record->status_sistema)) {
            $details['Estatus en sistema'] = (string) $record->status_sistema;
        }

        if (filled($record->status_convenio)) {
            $details['Convenio'] = (string) $record->status_convenio;
        }

        return $details;
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplierClasificacion', 'state', 'city']);
    }

    public static function form(Schema $schema): Schema
    {
        return DoctorNurseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DoctorNurseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorNursesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplierClasificacion', 'doctorNurseObservacions']);
    }

    public static function getRelations(): array
    {
        return [
            DoctorNurseObservacionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDoctorNurses::route('/'),
            'create' => CreateDoctorNurse::route('/create'),
            'view' => ViewDoctorNurse::route('/{record}'),
            'edit' => EditDoctorNurse::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'proveedores-naturales')->first();

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
