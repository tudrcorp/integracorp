<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TelegramRecipients;

use App\Enums\TelegramRecipientStatus;
use App\Filament\Business\Resources\TelegramRecipients\Pages\ListTelegramRecipients;
use App\Filament\Business\Resources\TelegramRecipients\Tables\TelegramRecipientsTable;
use App\Models\TelegramRecipient;
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
 * Destinos de Telegram de las notificaciones internas: personal vinculado,
 * contactos externos autorizados y grupos (que se aprueban aquí). Solo SUPERADMIN.
 */
class TelegramRecipientResource extends Resource
{
    protected static ?string $model = TelegramRecipient::class;

    protected static ?string $slug = 'telegram';

    protected static ?string $navigationLabel = 'Telegram';

    protected static ?string $modelLabel = 'destino de Telegram';

    protected static ?string $pluralModelLabel = 'destinos de Telegram';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 17;

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

    public static function getNavigationBadge(): ?string
    {
        if (! self::userIsSuperAdmin()) {
            return null;
        }

        $pending = TelegramRecipient::query()->groups()->where('status', TelegramRecipientStatus::PendingApproval->value)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Grupos esperando aprobación';
    }

    public static function table(Table $table): Table
    {
        return TelegramRecipientsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        if (! self::userIsSuperAdmin()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->with(['user:id,name,email,phone,status', 'approver:id,name']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramRecipients::route('/'),
        ];
    }

    public static function userIsSuperAdmin(): bool
    {
        $user = Auth::user();

        return $user instanceof User && UserNavigationAccess::isSuperAdmin($user);
    }
}
