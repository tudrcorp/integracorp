<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\Collection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fuente única de la fecha de vencimiento de una cuota y de sus días.
 *
 * La fecha oficial es `collections.next_payment_date` (la que ve y edita el analista
 * y la que se imprime en el aviso de cobro). Cada vez que se guarda la cuota
 * ({@see syncColumns()}) se derivan de ella:
 * - `filter_next_payment_date`: su copia en formato fecha para filtrar y ordenar en SQL.
 * - `expiration_date`: regla de negocio, siempre igual a la oficial. Algunos flujos
 *   viejos le sumaban 5 o 30 días; el guardado lo corrige.
 *
 * Los días no se guardan: se calculan contra la fecha de hoy cada vez que se leen,
 * por eso nunca quedan viejos.
 */
final class CollectionDueDate
{
    public const DISPLAY_FORMAT = 'd/m/Y';

    public const STORAGE_FORMAT = 'Y-m-d';

    public const PENDING_STATUS = 'POR PAGAR';

    public const STATUS_OVERDUE = 'VENCIDO';

    /**
     * Acepta los formatos que hay en la base: `dd/mm/aaaa` (oficial), `dd-mm-aaaa`
     * (algunas cuotas viejas) y `aaaa-mm-dd` (con o sin hora).
     */
    public static function parse(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        $formats = [
            '#^\d{1,2}/\d{1,2}/\d{4}$#' => 'd/m/Y',
            '#^\d{1,2}-\d{1,2}-\d{4}$#' => 'd-m-Y',
            '#^\d{4}-\d{1,2}-\d{1,2}$#' => 'Y-m-d',
            '#^\d{4}-\d{1,2}-\d{1,2}[ T]\d{1,2}:\d{2}(:\d{2})?$#' => null,
        ];

        foreach ($formats as $pattern => $format) {
            if (preg_match($pattern, $value) !== 1) {
                continue;
            }

            try {
                $date = $format === null
                    ? CarbonImmutable::parse($value)
                    : CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                return null;
            }

            if ($date === false) {
                return null;
            }

            /** createFromFormat acepta 31/02 desbordando al mes siguiente: se rechaza. */
            if ($format !== null && $date->format($format) !== self::zeroPad($value, $format)) {
                return null;
            }

            return $date->startOfDay();
        }

        return null;
    }

    /**
     * Fecha de vencimiento de la cuota: la oficial; la de filtro solo si la oficial
     * está vacía o es ilegible.
     */
    public static function of(Collection $collection): ?CarbonImmutable
    {
        return self::parse($collection->next_payment_date) ?? self::parse($collection->filter_next_payment_date);
    }

    /**
     * Deja las tres columnas de fecha coherentes a partir de la oficial. Se llama al
     * guardar la cuota, por cualquier vía (acciones, controladores, jobs, tinker).
     */
    public static function syncColumns(Collection $collection): void
    {
        $official = self::parse($collection->next_payment_date);

        if ($official === null && blank($collection->next_payment_date)) {
            $official = self::parse($collection->filter_next_payment_date);
        }

        if ($official === null) {
            return;
        }

        $collection->next_payment_date = $official->format(self::DISPLAY_FORMAT);
        $collection->filter_next_payment_date = $official->format(self::STORAGE_FORMAT);
        $collection->expiration_date = $official->format(self::DISPLAY_FORMAT);
    }

    /**
     * Días hasta el vencimiento: negativo si ya venció (días de atraso).
     */
    public static function daysUntil(Collection $collection, ?CarbonImmutable $today = null): ?int
    {
        $due = self::of($collection);

        if ($due === null) {
            return null;
        }

        return (int) ($today ?? CarbonImmutable::today())->startOfDay()->diffInDays($due, false);
    }

    public static function isPending(Collection $collection): bool
    {
        return Str::upper(trim((string) $collection->status)) === self::PENDING_STATUS;
    }

    public static function isOverdue(Collection $collection, ?CarbonImmutable $today = null): bool
    {
        $days = self::daysUntil($collection, $today);

        return self::isPending($collection) && $days !== null && $days < 0;
    }

    /**
     * Estatus de cobro de una cuota pendiente: VENCIDO o POR PAGAR.
     */
    public static function collectionStatus(Collection $collection, ?CarbonImmutable $today = null): string
    {
        return self::isOverdue($collection, $today) ? self::STATUS_OVERDUE : self::PENDING_STATUS;
    }

    /**
     * Estado para mostrar: el guardado, salvo POR PAGAR vencida, que se muestra VENCIDO.
     */
    public static function displayStatus(Collection $collection, ?CarbonImmutable $today = null): string
    {
        if (self::isOverdue($collection, $today)) {
            return self::STATUS_OVERDUE;
        }

        $status = Str::upper(trim((string) $collection->status));

        return $status !== '' ? $status : 'SIN ESTADO';
    }

    /**
     * Días en positivo: de atraso si está vencida, para vencer si no.
     */
    public static function daysCount(Collection $collection, ?CarbonImmutable $today = null): ?int
    {
        $days = self::daysUntil($collection, $today);

        return $days === null ? null : abs($days);
    }

    public static function daysLabel(Collection $collection, ?CarbonImmutable $today = null): ?string
    {
        if (! self::isPending($collection)) {
            return null;
        }

        $days = self::daysUntil($collection, $today);

        return match (true) {
            $days === null => 'Sin fecha',
            $days < 0 => abs($days).' '.(abs($days) === 1 ? 'día' : 'días').' de atraso',
            $days === 0 => 'Vence hoy',
            default => 'Faltan '.$days.' '.($days === 1 ? 'día' : 'días'),
        };
    }

    private static function zeroPad(string $value, string $format): string
    {
        $separator = str_contains($format, '/') ? '/' : '-';
        $parts = explode($separator, $value);

        return implode($separator, array_map(
            fn (string $part): string => strlen($part) === 4 ? $part : str_pad($part, 2, '0', STR_PAD_LEFT),
            $parts,
        ));
    }
}
