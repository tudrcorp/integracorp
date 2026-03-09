<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications;

use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\CreateBirthdayNotification;
use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\EditBirthdayNotification;
use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\ListBirthdayNotifications;
use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\ViewBirthdayNotification;
use App\Filament\Marketing\Resources\BirthdayNotifications\Schemas\BirthdayNotificationForm;
use App\Filament\Marketing\Resources\BirthdayNotifications\Schemas\BirthdayNotificationInfolist;
use App\Filament\Marketing\Resources\BirthdayNotifications\Tables\BirthdayNotificationsTable;
use App\Models\BirthdayNotification;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BirthdayNotificationResource extends Resource
{
    protected static ?string $model = BirthdayNotification::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-cake';

    protected static ?string $navigationLabel = 'Notificaciones Cumpleaños';

    protected static string|UnitEnum|null $navigationGroup = 'MARKETING';

    public static function form(Schema $schema): Schema
    {
        return BirthdayNotificationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BirthdayNotificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BirthdayNotificationsTable::configure($table);
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
            'index' => ListBirthdayNotifications::route('/'),
            'create' => CreateBirthdayNotification::route('/create'),
            'view' => ViewBirthdayNotification::route('/{record}'),
            'edit' => EditBirthdayNotification::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'notificaciones-cumpleaños')->first();

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
