<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Vigencia y caducidad de las órdenes de servicio.
 *
 * Si las órdenes caducan y en cuántos días lo decide la configuración de
 * Operaciones → Parámetros de órdenes (OperationServiceOrderSetting). Con la
 * caducidad apagada ninguna orden abierta vence ni muestra cuenta regresiva; las
 * que ya quedaron CADUCADAS siguen así.
 */
final class OperationServiceOrderValidity
{
    /** Regla histórica: vale mientras no haya configuración guardada o no se pueda leer. */
    public const DEFAULT_VALIDITY_DAYS = 10;

    public const MIN_VALIDITY_DAYS = 1;

    public const MAX_VALIDITY_DAYS = 365;

    /**
     * @deprecated Los días son configurables: usar validityDays().
     */
    public const VALIDITY_DAYS = self::DEFAULT_VALIDITY_DAYS;

    /** Segundos que se reutiliza la configuración leída (los workers de cola viven horas). */
    private const SETTINGS_TTL_SECONDS = 30;

    /**
     * @var array{enabled: bool, days: int}|null
     */
    private static ?array $settings = null;

    private static int $settingsLoadedAt = 0;

    /**
     * @var array{enabled: bool, days: int}|null
     */
    private static ?array $settingsOverride = null;

    public const STATUS_EXPIRED = 'CADUCADA';

    public static function expirationEnabled(): bool
    {
        return self::settings()['enabled'];
    }

    public static function validityDays(): int
    {
        return self::settings()['days'];
    }

    public static function clampDays(int $days): int
    {
        return max(self::MIN_VALIDITY_DAYS, min(self::MAX_VALIDITY_DAYS, $days));
    }

    /**
     * Descarta la configuración memorizada: llamar después de guardarla y al
     * empezar el job diario.
     */
    public static function flushSettings(): void
    {
        self::$settings = null;
        self::$settingsLoadedAt = 0;
    }

    /**
     * Fija la configuración sin tocar la base (pruebas). null vuelve a leerla.
     */
    public static function overrideSettings(?bool $enabled, ?int $days = null): void
    {
        self::$settingsOverride = $enabled === null
            ? null
            : ['enabled' => $enabled, 'days' => self::clampDays($days ?? self::DEFAULT_VALIDITY_DAYS)];

        self::flushSettings();
    }

    /**
     * @return array{enabled: bool, days: int}
     */
    private static function settings(): array
    {
        if (self::$settingsOverride !== null) {
            return self::$settingsOverride;
        }

        if (self::$settings !== null && (time() - self::$settingsLoadedAt) < self::SETTINGS_TTL_SECONDS) {
            return self::$settings;
        }

        $settings = ['enabled' => true, 'days' => self::DEFAULT_VALIDITY_DAYS];

        try {
            $row = OperationServiceOrderSetting::query()->orderBy('id')->first();

            if ($row instanceof OperationServiceOrderSetting) {
                $settings = ['enabled' => (bool) $row->expiration_enabled, 'days' => $row->validityDays()];
            }
        } catch (Throwable) {
            // Sin base o sin la tabla todavía: se mantiene la regla histórica.
        }

        self::$settingsLoadedAt = time();

        return self::$settings = $settings;
    }

    /**
     * @return list<string>
     */
    public static function closedStatuses(): array
    {
        return [
            'FINALIZADO',
            'CANCELADA',
            'CANCELADO',
            self::STATUS_EXPIRED,
        ];
    }

    public static function approvedAt(OperationServiceOrder $order): ?Carbon
    {
        $attributes = $order->getAttributes();

        if (array_key_exists('approved_at', $attributes) && $attributes['approved_at'] !== null) {
            return Carbon::parse($attributes['approved_at']);
        }

        if (array_key_exists('created_at', $attributes) && $attributes['created_at'] !== null) {
            return Carbon::parse($attributes['created_at']);
        }

        return null;
    }

    public static function expiresAt(OperationServiceOrder $order): ?Carbon
    {
        $approvedAt = self::approvedAt($order);

        if ($approvedAt === null) {
            return null;
        }

        return $approvedAt->copy()->addDays(self::validityDays())->endOfDay();
    }

    public static function isSubjectToExpiration(OperationServiceOrder $order): bool
    {
        if (! self::expirationEnabled()) {
            return false;
        }

        $status = mb_strtoupper(trim((string) ($order->status ?? '')));

        return $status !== '' && ! in_array($status, self::closedStatuses(), true);
    }

    public static function isExpired(OperationServiceOrder $order): bool
    {
        if (! self::isSubjectToExpiration($order)) {
            return false;
        }

        $expiresAt = self::expiresAt($order);

        return $expiresAt !== null && now()->greaterThan($expiresAt);
    }

    public static function remainingDays(OperationServiceOrder $order): ?int
    {
        if (! self::isSubjectToExpiration($order)) {
            return null;
        }

        $expiresAt = self::expiresAt($order);

        if ($expiresAt === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false);
    }

    public static function shouldHighlightVigencia(OperationServiceOrder $order): bool
    {
        return self::vigenciaTone($order) !== null;
    }

    /**
     * @return 'danger'|'warning'|'info'|null
     */
    public static function vigenciaTone(OperationServiceOrder $order): ?string
    {
        $status = mb_strtoupper(trim((string) ($order->status ?? '')));

        if ($status === self::STATUS_EXPIRED) {
            return 'danger';
        }

        if (! self::isSubjectToExpiration($order)) {
            return null;
        }

        $remaining = self::remainingDays($order);

        if ($remaining === null) {
            return null;
        }

        if ($remaining <= 0) {
            return 'danger';
        }

        if ($remaining <= 2) {
            return 'warning';
        }

        return 'info';
    }

    public static function vigenciaShortLabel(OperationServiceOrder $order): ?string
    {
        $status = mb_strtoupper(trim((string) ($order->status ?? '')));

        if ($status === self::STATUS_EXPIRED) {
            return 'Vigencia vencida';
        }

        if (! self::isSubjectToExpiration($order)) {
            return null;
        }

        $remaining = self::remainingDays($order);

        if ($remaining === null) {
            return null;
        }

        if ($remaining < 0) {
            return 'Vigencia vencida';
        }

        if ($remaining === 0) {
            return 'Vence hoy';
        }

        if ($remaining === 1) {
            return 'Vence en 1 día';
        }

        return sprintf('Vence en %d días', $remaining);
    }

    public static function vigenciaLabel(OperationServiceOrder $order): string
    {
        $status = mb_strtoupper(trim((string) ($order->status ?? '')));

        if ($status === self::STATUS_EXPIRED) {
            return 'Vigencia vencida · caducada automáticamente';
        }

        if (! self::isSubjectToExpiration($order)) {
            return '—';
        }

        $approvedAt = self::approvedAt($order);
        $expiresAt = self::expiresAt($order);

        if ($approvedAt === null || $expiresAt === null) {
            return '—';
        }

        $remaining = self::remainingDays($order);

        if ($remaining === null) {
            return '—';
        }

        if ($remaining < 0) {
            return sprintf(
                'Aprobada %s · venció %s',
                $approvedAt->format('d/m/Y'),
                $expiresAt->format('d/m/Y')
            );
        }

        if ($remaining === 0) {
            return sprintf(
                'Aprobada %s · vence hoy (%s)',
                $approvedAt->format('d/m/Y'),
                $expiresAt->format('d/m/Y')
            );
        }

        return sprintf(
            'Aprobada %s · vence %s (%d día(s) restante(s))',
            $approvedAt->format('d/m/Y'),
            $expiresAt->format('d/m/Y'),
            $remaining
        );
    }

    /**
     * @param  Builder<OperationServiceOrder>  $query
     * @return Builder<OperationServiceOrder>
     */
    public static function scopeExpirable(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::closedStatuses());
    }

    public static function expireIfNeeded(OperationServiceOrder $order, string $updatedBy = 'system'): bool
    {
        if (! self::isExpired($order)) {
            return false;
        }

        $order->update([
            'status' => self::STATUS_EXPIRED,
            'updated_by' => $updatedBy,
        ]);

        return true;
    }

    /**
     * Ventana mínima entre barridos disparados desde la interfaz.
     */
    public const SWEEP_THROTTLE_SECONDS = 600;

    /**
     * Barrido de caducidad pensado para llamarse desde una pantalla.
     *
     * `expireEligibleOrders()` recorre y actualiza todas las órdenes vigentes, y
     * la tabla de órdenes lo invocaba en cada render: cada paginación, cada
     * búsqueda y cada cambio de orden repetían el trabajo completo. Aquí se
     * limita a una vez cada diez minutos por proceso; el barrido garantizado lo
     * sigue haciendo el scheduler con `ExpireOperationServiceOrders`.
     */
    public static function expireEligibleOrdersThrottled(string $updatedBy = 'system'): void
    {
        if (! self::expirationEnabled()) {
            return;
        }

        try {
            if (! Cache::add('operation-service-orders:expiry-sweep', true, self::SWEEP_THROTTLE_SECONDS)) {
                return;
            }
        } catch (Throwable) {
            // Sin caché disponible se prefiere no bloquear el render de la tabla.
            return;
        }

        self::expireEligibleOrders($updatedBy);
    }

    /**
     * Órdenes abiertas que ya superaron el plazo de `$days` días. Una orden vence al
     * terminar el día `aprobación + días`, así que caduca si se aprobó antes del
     * inicio del día `hoy − días`.
     *
     * @return Builder<OperationServiceOrder>
     */
    public static function overdueQuery(int $days): Builder
    {
        $cutoff = now()->startOfDay()->subDays(self::clampDays($days));

        return OperationServiceOrder::query()
            ->whereNotIn('status', self::closedStatuses())
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->where(function (Builder $query) use ($cutoff): void {
                $query
                    ->where(function (Builder $inner) use ($cutoff): void {
                        $inner->whereNotNull('approved_at')
                            ->where('approved_at', '<', $cutoff);
                    })
                    ->orWhere(function (Builder $inner) use ($cutoff): void {
                        $inner->whereNull('approved_at')
                            ->where('created_at', '<', $cutoff);
                    });
            });
    }

    /**
     * Cuántas órdenes abiertas caducarían con un plazo de `$days` días (para avisar
     * antes de guardar un plazo más corto).
     */
    public static function countOverdueWith(int $days): int
    {
        try {
            return self::overdueQuery($days)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public static function expireEligibleOrders(string $updatedBy = 'system'): int
    {
        if (! self::expirationEnabled()) {
            return 0;
        }

        $expiredCount = 0;

        self::overdueQuery(self::validityDays())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$expiredCount, $updatedBy): void {
                foreach ($orders as $order) {
                    if (! $order instanceof OperationServiceOrder) {
                        continue;
                    }

                    if (self::expireIfNeeded($order, $updatedBy)) {
                        $expiredCount++;
                    }
                }
            });

        return $expiredCount;
    }
}
