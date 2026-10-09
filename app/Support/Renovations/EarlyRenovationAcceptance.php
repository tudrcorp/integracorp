<?php

declare(strict_types=1);

namespace App\Support\Renovations;

use App\Jobs\PrepareAffiliationRenovations;
use App\Models\Renovation;
use App\Models\RenovationCorporate;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Renovación anticipada: aceptar una renovación que todavía **no** está en
 * período de renovación (faltan más de 30 días para su fecha).
 *
 * La regla de los 30 días no cambia —el job diario sigue pasando las renovaciones
 * a «PERIODO DE RENOVACION» igual que antes—. Esto es una excepción que solo puede
 * hacer quien tenga el permiso `aceptar-renovacion-anticipada`, con un motivo
 * obligatorio; queda en el histórico y se avisa a los SUPERADMIN.
 */
final class EarlyRenovationAcceptance
{
    public const PERMISSION = BusinessFilamentActionPermissionRegistry::ACCEPT_EARLY_RENOVATION;

    public const RENEWAL_PERIOD_DAYS = PrepareAffiliationRenovations::RENEWAL_PERIOD_DAYS;

    public const MIN_REASON_LENGTH = 15;

    public const MAX_REASON_LENGTH = 1000;

    /**
     * Permiso ya evaluado en este request, por usuario y panel: la tabla pregunta
     * en cada fila y no debe consultar los permisos una vez por fila.
     *
     * @var array<string, bool>
     */
    private static array $permissionCache = [];

    public static function isEarly(Renovation|RenovationCorporate $renovation): bool
    {
        return $renovation->status !== PrepareAffiliationRenovations::STATUS_RENOVATION_PERIOD;
    }

    /**
     * Días que faltan para la fecha de renovación, calculados desde `date_renewal`
     * (no desde `remaining_days`, que lo recalcula un job y puede venir desfasado).
     */
    public static function daysUntilRenewal(Renovation|RenovationCorporate $renovation, ?Carbon $today = null): ?int
    {
        if ($renovation->date_renewal === null) {
            return null;
        }

        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        return (int) $today->diffInDays($renovation->date_renewal->copy()->startOfDay(), absolute: false);
    }

    /**
     * Si el usuario autenticado puede renovar anticipadamente en el panel actual.
     */
    public static function currentUserCan(): bool
    {
        $userId = Auth::id();

        if ($userId === null) {
            return false;
        }

        try {
            $panelId = Filament::getCurrentPanel()?->getId() ?? '-';
        } catch (Throwable) {
            $panelId = '-';
        }

        return self::$permissionCache[$userId.'|'.$panelId] ??= BusinessFilamentActionAccess::userCan(self::PERMISSION);
    }

    public static function flushPermissionCache(): void
    {
        self::$permissionCache = [];
    }

    /**
     * Arma la autorización desde el formulario. Devuelve `null` si el formulario no
     * trae la confirmación, y **lanza** si la trae pero el usuario no tiene permiso
     * o el motivo no es válido: la visibilidad del campo no basta como control.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException
     */
    public static function authorizationFromFormData(array $data, ?User $user = null): ?EarlyRenovationAuthorization
    {
        if (! (bool) ($data['early_confirmed'] ?? false)) {
            return null;
        }

        $user ??= Auth::user();

        if (! $user instanceof User || ! self::currentUserCan()) {
            throw new \InvalidArgumentException('No tiene permiso para renovar antes del período de renovación.');
        }

        $reason = self::normalizeReason($data['early_reason'] ?? null);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new \InvalidArgumentException('Indique el motivo de la renovación anticipada (mínimo '.self::MIN_REASON_LENGTH.' caracteres).');
        }

        return new EarlyRenovationAuthorization(
            reason: Str::limit($reason, self::MAX_REASON_LENGTH, ''),
            userId: (int) $user->getKey(),
            userName: (string) ($user->name ?? 'Usuario '.$user->getKey()),
            userEmail: $user->email !== null ? (string) $user->email : null,
        );
    }

    public static function normalizeReason(mixed $reason): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $reason)));
    }

    /**
     * Mensaje cuando se intenta aceptar sin autorización una renovación fuera de período.
     */
    public static function blockedMessage(Renovation|RenovationCorporate $renovation): string
    {
        $days = self::daysUntilRenewal($renovation);
        $when = $days === null ? 'sin fecha de renovación' : ($days === 1 ? 'falta 1 día' : "faltan {$days} días");

        return "Renovación {$renovation->code_affiliation}: está fuera del período de renovación ({$when}; el período abre a "
            .self::RENEWAL_PERIOD_DAYS.' días). Para aceptarla antes de tiempo se requiere el permiso de renovación anticipada, confirmarla e indicar el motivo.';
    }
}
