<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

/**
 * Voucher ILS obligatorio al agregar un upgrade a un afiliado (individual o
 * corporativo). Queda en la fila del upgrade asignado: número, vigencia y
 * comprobante.
 *
 * Reglas, todas en el servidor:
 * - número obligatorio, sin repetirse en todo el sistema (upgrades, vouchers de
 *   afiliados individuales y vouchers corporativos), sin distinguir mayúsculas;
 * - «hasta» igual o posterior a «desde» y no vencido;
 * - comprobante subido al disco de vouchers.
 */
final class UpgradeIlsVoucher
{
    public const DOCUMENT_DISK = 'public';

    public const DOCUMENT_DIRECTORY = 'upgrades-ils';

    public const MAX_CODE_LENGTH = 80;

    /**
     * Valida los vouchers de una asignación (uno por afiliado) y los deja listos
     * para guardar. Lanza con el primer problema, nombrando al afiliado.
     *
     * El mismo número de voucher puede repetirse entre afiliados y con vouchers ya
     * registrados (un voucher ILS de grupo cubre a varias personas; decisión del 29/10/2026).
     *
     * @param  array<int|string, mixed>  $vouchers  afiliado_id => datos del formulario
     * @param  array<int, string>  $labels  afiliado_id => nombre, para los mensajes
     * @return array<int, array{code: string, date_init: string, date_end: string, document_path: string}>
     */
    public static function validateMany(array $vouchers, array $labels = [], ?Carbon $today = null): array
    {
        $normalized = [];

        foreach ($vouchers as $affiliateId => $voucher) {
            $affiliateId = (int) $affiliateId;
            $who = $labels[$affiliateId] ?? 'el afiliado #'.$affiliateId;
            $normalized[$affiliateId] = self::validate(is_array($voucher) ? $voucher : [], $who, $today);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $voucher
     * @return array{code: string, date_init: string, date_end: string, document_path: string}
     */
    public static function validate(array $voucher, string $who = 'el afiliado', ?Carbon $today = null): array
    {
        $code = self::normalizeCode($voucher['code'] ?? null);
        $dateInit = self::parseDate($voucher['date_init'] ?? null);
        $dateEnd = self::parseDate($voucher['date_end'] ?? null);
        $document = self::documentPath($voucher['document_path'] ?? null);
        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        if ($code === '') {
            throw new InvalidArgumentException('Falta el número del Voucher ILS de '.$who.'.');
        }

        if (mb_strlen($code) > self::MAX_CODE_LENGTH) {
            throw new InvalidArgumentException('El número del Voucher ILS de '.$who.' supera los '.self::MAX_CODE_LENGTH.' caracteres.');
        }

        if ($dateInit === null || $dateEnd === null) {
            throw new InvalidArgumentException('Indique la vigencia (desde y hasta) del Voucher ILS de '.$who.'.');
        }

        if ($dateEnd->lt($dateInit)) {
            throw new InvalidArgumentException('En el Voucher ILS de '.$who.', «hasta» no puede ser anterior a «desde».');
        }

        if ($dateEnd->lt($today)) {
            throw new InvalidArgumentException('El Voucher ILS de '.$who.' venció el '.$dateEnd->format('d/m/Y').'. Cargue un voucher vigente.');
        }

        if ($document === null) {
            throw new InvalidArgumentException('Adjunte el comprobante del Voucher ILS de '.$who.'.');
        }

        return [
            'code' => $code,
            'date_init' => $dateInit->toDateString(),
            'date_end' => $dateEnd->toDateString(),
            'document_path' => $document,
        ];
    }

    /**
     * Si el número ya existe en algún voucher del sistema.
     */
    public static function isCodeTaken(string $code): bool
    {
        $code = mb_strtoupper(self::normalizeCode($code), 'UTF-8');

        if ($code === '') {
            return false;
        }

        foreach ([
            ['affiliate_upgrades', 'ils_voucher_code'],
            ['affiliate_corporate_upgrades', 'ils_voucher_code'],
            ['affiliates', 'vaucherIls'],
            ['affiliate_corporates', 'vaucherIls'],
            ['affiliate_corporate_ils_vouchers', 'voucher_code'],
        ] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            if (DB::table($table)->whereRaw('UPPER(TRIM('.$column.')) = ?', [$code])->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Columnas de la fila del upgrade asignado.
     *
     * @param  array{code: string, date_init: string, date_end: string, document_path: string}  $voucher
     * @return array<string, string>
     */
    public static function columns(array $voucher): array
    {
        return [
            'ils_voucher_code' => $voucher['code'],
            'ils_date_init' => $voucher['date_init'],
            'ils_date_end' => $voucher['date_end'],
            'ils_document_path' => $voucher['document_path'],
        ];
    }

    /**
     * Días de vigencia que le quedan (0 si venció), o null sin fecha.
     */
    public static function remainingDays(mixed $dateEnd, ?Carbon $today = null): ?int
    {
        $end = self::parseDate($dateEnd);

        if ($end === null) {
            return null;
        }

        $today = ($today ?? Carbon::today())->copy()->startOfDay();

        return $end->lt($today) ? 0 : (int) $today->diffInDays($end);
    }

    public static function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $value = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);

                if ($parsed !== false && $parsed->format($format) === $value) {
                    return $parsed->startOfDay();
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    private static function normalizeCode(mixed $code): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', is_scalar($code) ? (string) $code : ''));
    }

    /**
     * Ruta del comprobante en el disco, sin trucos de directorio y existente.
     */
    private static function documentPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = array_values(array_filter($value, 'is_string'))[0] ?? null;
        }

        $path = trim(is_string($value) ? $value : '');

        if ($path === '' || str_contains($path, '..') || ! Storage::disk(self::DOCUMENT_DISK)->exists($path)) {
            return null;
        }

        return $path;
    }
}
