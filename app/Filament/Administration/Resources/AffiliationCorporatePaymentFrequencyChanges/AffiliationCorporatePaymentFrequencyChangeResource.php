<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges;

use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\Pages\ListAffiliationCorporatePaymentFrequencyChanges;
use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\Pages\ViewAffiliationCorporatePaymentFrequencyChange;
use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\Schemas\AffiliationCorporatePaymentFrequencyChangeInfolist;
use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\Tables\AffiliationCorporatePaymentFrequencyChangesTable;
use App\Models\AffiliationCorporatePaymentFrequencyChange;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChangeRecipients;
use App\Support\Filament\UserNavigationAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

/**
 * Bitácora de cambios de frecuencia de pago hechos desde Negocios.
 *
 * La ve cualquier usuario de Administración (así el enlace de la notificación
 * nunca termina en 403); validar también. Revertir exige un permiso aparte.
 */
class AffiliationCorporatePaymentFrequencyChangeResource extends Resource
{
    protected static ?string $model = AffiliationCorporatePaymentFrequencyChange::class;

    protected static ?string $navigationLabel = 'Cambios de frecuencia de pago';

    protected static ?string $modelLabel = 'cambio de frecuencia de pago';

    protected static ?string $pluralModelLabel = 'cambios de frecuencia de pago';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return UserNavigationAccess::isSuperAdmin($user)
            || in_array(CorporatePaymentFrequencyChangeRecipients::DEPARTMENT, CorporatePaymentFrequencyChangeRecipients::departmentsOf($user), true);
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $pending = AffiliationCorporatePaymentFrequencyChange::query()
                ->where('status', AffiliationCorporatePaymentFrequencyChange::STATUS_APPLIED)
                ->whereNull('validated_at')
                ->count();
        } catch (Throwable) {
            return null;
        }

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Cambios por validar';
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliationCorporatePaymentFrequencyChangeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliationCorporatePaymentFrequencyChangesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliationCorporatePaymentFrequencyChanges::route('/'),
            'view' => ViewAffiliationCorporatePaymentFrequencyChange::route('/{record}'),
        ];
    }
}
