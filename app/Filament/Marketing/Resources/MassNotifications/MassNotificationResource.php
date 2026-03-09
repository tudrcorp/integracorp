<?php

namespace App\Filament\Marketing\Resources\MassNotifications;

use App\Filament\Marketing\Resources\MassNotifications\Pages\CreateMassNotification;
use App\Filament\Marketing\Resources\MassNotifications\Pages\EditMassNotification;
use App\Filament\Marketing\Resources\MassNotifications\Pages\ListMassNotifications;
use App\Filament\Marketing\Resources\MassNotifications\Pages\ViewMassNotification;
use App\Filament\Marketing\Resources\MassNotifications\RelationManagers\DataNotificationsRelationManager;
use App\Filament\Marketing\Resources\MassNotifications\Schemas\MassNotificationForm;
use App\Filament\Marketing\Resources\MassNotifications\Schemas\MassNotificationInfolist;
use App\Filament\Marketing\Resources\MassNotifications\Tables\MassNotificationsTable;
use App\Models\MassNotification;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MassNotificationResource extends Resource
{
    protected static ?string $model = MassNotification::class;

    // protected static string|BackedEnum|null $navigationIcon = 'fontisto-navigate';

    protected static ?string $navigationLabel = 'Notificaciones Masivas';

    protected static string|UnitEnum|null $navigationGroup = 'MARKETING';

    public static function form(Schema $schema): Schema
    {
        return MassNotificationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MassNotificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MassNotificationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DataNotificationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMassNotifications::route('/'),
            'create' => CreateMassNotification::route('/create'),
            'view' => ViewMassNotification::route('/{record}'),
            'edit' => EditMassNotification::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'notificaciones-masivas')->first();

        // si es superadmin, retornar true
        if (in_array('SUPERADMIN', Auth::user()->departament)) {
            return true;
        }

        if (in_array($module, Auth::user()->departament)) {
            if (UserPermission::where('user_id', Auth::user()->id)->where('permission_id', $permission->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
