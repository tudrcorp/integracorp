<?php

namespace App\Filament\Marketing\Resources\DataNotifications;

use App\Filament\Marketing\Resources\DataNotifications\Pages\CreateDataNotification;
use App\Filament\Marketing\Resources\DataNotifications\Pages\EditDataNotification;
use App\Filament\Marketing\Resources\DataNotifications\Pages\ListDataNotifications;
use App\Filament\Marketing\Resources\DataNotifications\Pages\ViewDataNotification;
use App\Filament\Marketing\Resources\DataNotifications\Schemas\DataNotificationForm;
use App\Filament\Marketing\Resources\DataNotifications\Schemas\DataNotificationInfolist;
use App\Filament\Marketing\Resources\DataNotifications\Tables\DataNotificationsTable;
use App\Models\DataNotification;
use App\Models\Permission;
use App\Models\UserPermission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DataNotificationResource extends Resource
{
    protected static ?string $model = DataNotification::class;

    // protected static string|BackedEnum|null $navigationIcon = 'heroicon-m-squares-plus';

    protected static ?string $navigationLabel = 'Destinatarios';

    protected static string|UnitEnum|null $navigationGroup = 'MARKETING';

    public static function form(Schema $schema): Schema
    {
        return DataNotificationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DataNotificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DataNotificationsTable::configure($table);
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
            'index' => ListDataNotifications::route('/'),
            'create' => CreateDataNotification::route('/create'),
            'view' => ViewDataNotification::route('/{record}'),
            'edit' => EditDataNotification::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $module = 'MARKETING';
        $permission = Permission::where('module', $module)->where('slug', 'destinatarios')->first();

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
