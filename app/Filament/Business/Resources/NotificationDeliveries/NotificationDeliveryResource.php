<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\NotificationDeliveries;

use App\Filament\Business\Resources\NotificationDeliveries\Pages\ListNotificationDeliveries;
use App\Filament\Business\Resources\NotificationDeliveries\Tables\NotificationDeliveriesTable;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Bitácora de envíos de las notificaciones internas: por qué canal salió cada
 * aviso, los intentos y por qué pasó al respaldo. Solo SUPERADMIN; solo lectura.
 */
class NotificationDeliveryResource extends Resource
{
    protected static ?string $model = NotificationDelivery::class;

    protected static ?string $slug = 'bitacora-notificaciones';

    protected static ?string $navigationLabel = 'Bitácora de notificaciones';

    protected static ?string $modelLabel = 'envío';

    protected static ?string $pluralModelLabel = 'envíos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 18;

    public static function shouldRegisterNavigation(): bool
    {
        return self::userIsSuperAdmin();
    }

    public static function canAccess(): bool
    {
        return self::userIsSuperAdmin();
    }

    public static function canViewAny(): bool
    {
        return self::userIsSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return NotificationDeliveriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        if (! self::userIsSuperAdmin()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->with(['recipient:id,name,type', 'parent:id,channel,status']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationDeliveries::route('/'),
        ];
    }

    public static function userIsSuperAdmin(): bool
    {
        $user = Auth::user();

        return $user instanceof User && UserNavigationAccess::isSuperAdmin($user);
    }
}
