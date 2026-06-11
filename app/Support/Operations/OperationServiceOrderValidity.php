<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationServiceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class OperationServiceOrderValidity
{
    public const VALIDITY_DAYS = 10;

    public const STATUS_EXPIRED = 'CADUCADA';

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

        return $approvedAt->copy()->addDays(self::VALIDITY_DAYS)->endOfDay();
    }

    public static function isSubjectToExpiration(OperationServiceOrder $order): bool
    {
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

    public static function expireEligibleOrders(string $updatedBy = 'system'): int
    {
        $cutoff = now()->subDays(self::VALIDITY_DAYS);
        $expiredCount = 0;

        OperationServiceOrder::query()
            ->whereNotIn('status', self::closedStatuses())
            ->where(function (Builder $query) use ($cutoff): void {
                $query
                    ->where(function (Builder $inner) use ($cutoff): void {
                        $inner->whereNotNull('approved_at')
                            ->where('approved_at', '<=', $cutoff);
                    })
                    ->orWhere(function (Builder $inner) use ($cutoff): void {
                        $inner->whereNull('approved_at')
                            ->where('created_at', '<=', $cutoff);
                    });
            })
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
