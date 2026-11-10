<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\SystemOtpCodes;

use App\Filament\Business\Resources\SystemOtpCodes\Pages\ListSystemOtpCodes;
use App\Filament\Business\Resources\SystemOtpCodes\Tables\SystemOtpCodesTable;
use App\Models\SystemOtpCode;
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
 * Claves OTP de las últimas 24 horas (población corporativa, servicio fuera de
 * cupo y acceso a cupos clínicos), para que un SUPERADMIN pueda dictarlas si el
 * WhatsApp o el correo no llegan. Solo SUPERADMIN; solo lectura.
 */
class SystemOtpCodeResource extends Resource
{
    protected static ?string $model = SystemOtpCode::class;

    protected static ?string $slug = 'claves-otp';

    protected static ?string $navigationLabel = 'Claves OTP';

    protected static ?string $modelLabel = 'clave OTP';

    protected static ?string $pluralModelLabel = 'claves OTP';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 91;

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
        return SystemOtpCodesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        if (! self::userIsSuperAdmin()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->withinRetention()
            ->withReplacedFlag()
            ->with(['challenge']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystemOtpCodes::route('/'),
        ];
    }

    public static function userIsSuperAdmin(): bool
    {
        $user = Auth::user();

        return $user instanceof User && UserNavigationAccess::isSuperAdmin($user);
    }
}
