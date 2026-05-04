<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers;

use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\CreateOperationOnCallUser;
use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\EditOperationOnCallUser;
use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\ListOperationOnCallUsers;
use App\Filament\Operations\Resources\OperationOnCallUsers\Pages\ViewOperationOnCallUser;
use App\Filament\Operations\Resources\OperationOnCallUsers\Schemas\OperationOnCallUserForm;
use App\Filament\Operations\Resources\OperationOnCallUsers\Schemas\OperationOnCallUserInfolist;
use App\Filament\Operations\Resources\OperationOnCallUsers\Tables\OperationOnCallUsersTable;
use App\Models\OperationOnCallUser;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use UnitEnum;

class OperationOnCallUserResource extends Resource
{
    protected static ?string $model = OperationOnCallUser::class;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACION';

    protected static ?string $navigationLabel = 'Roles de Guardia';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    public static function form(Schema $schema): Schema
    {
        return OperationOnCallUserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OperationOnCallUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationOnCallUsersTable::configure($table);
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
            'index' => ListOperationOnCallUsers::route('/'),
            'create' => CreateOperationOnCallUser::route('/create'),
            'view' => ViewOperationOnCallUser::route('/{record}'),
            'edit' => EditOperationOnCallUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::userMayAccessGuardDutyRoles();
    }

    public static function canView(Model $record): bool
    {
        return self::userMayAccessGuardDutyRoles();
    }

    public static function canCreate(): bool
    {
        return self::userMayAccessGuardDutyRoles();
    }

    public static function canEdit(Model $record): bool
    {
        return self::userMayAccessGuardDutyRoles();
    }

    public static function canDelete(Model $record): bool
    {
        return self::userMayAccessGuardDutyRoles();
    }

    public static function canDeleteAny(): bool
    {
        return self::userMayAccessGuardDutyRoles();
    }

    /**
     * Permiso `OPERACIONES` / slug `roles-de-guardia` + entrada en `user_permissions`,
     * o usuario con departamento SUPERADMIN. Requiere fila en `permissions` (semilla).
     */
    protected static function userMayAccessGuardDutyRoles(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        $departments = $user->departament ?? [];
        if (! is_array($departments)) {
            $departments = filled($departments) ? [$departments] : [];
        }

        if (in_array('SUPERADMIN', $departments, true)) {
            return true;
        }

        $permission = Permission::query()
            ->where('module', 'OPERACIONES')
            ->where('slug', 'roles-de-guardia')
            ->first();

        if ($permission === null) {
            Log::warning('OPERACIONES: permiso «roles-de-guardia» ausente; acceso a Roles de Guardia denegado.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        if (! in_array('OPERACIONES', $departments, true)) {
            return false;
        }

        return UserPermission::query()
            ->where('user_id', $user->id)
            ->where('permission_id', $permission->id)
            ->exists();
    }
}
