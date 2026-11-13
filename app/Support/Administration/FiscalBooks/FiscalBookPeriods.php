<?php

declare(strict_types=1);

namespace App\Support\Administration\FiscalBooks;

use App\Enums\FiscalBook;
use App\Models\FiscalBookPeriod;
use App\Models\FiscalBookPeriodEvent;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Meses de los libros fiscales: elegir el mes, saber si está cerrado, cerrarlo
 * y reabrirlo.
 *
 * Cerrar guarda los totales del momento y bloquea el mes. Reabrir exige el
 * permiso de acción y una razón, y queda en la bitácora y en SecurityAudit.
 * Ambas operaciones bloquean la fila del mes: dos analistas no lo cambian a la vez.
 */
final class FiscalBookPeriods
{
    public const FIRST_PERIOD = '2026-01-01';

    public const MIN_REASON_LENGTH = 10;

    public static function userCanCloseAndReopen(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::CLOSE_FISCAL_BOOKS);
    }

    public static function current(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * «2026-10» o «2026-10-01» → primer día del mes. Inválido o fuera de rango → mes actual.
     */
    public static function parse(mixed $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})(-\d{2})?$/', $value, $matches) === 1) {
            try {
                $period = CarbonImmutable::createFromDate((int) $matches[1], (int) $matches[2], 1)->startOfDay();

                if ($period->gte(CarbonImmutable::parse(self::FIRST_PERIOD)) && $period->lte(self::current()->addYear())) {
                    return $period;
                }
            } catch (Throwable) {
                return self::current();
            }
        }

        return self::current();
    }

    /**
     * Meses desde enero de 2026 hasta el mes actual, del más reciente al más antiguo.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        $cursor = self::current();
        $first = CarbonImmutable::parse(self::FIRST_PERIOD);

        while ($cursor->gte($first)) {
            $options[$cursor->format('Y-m')] = self::label($cursor);
            $cursor = $cursor->subMonthNoOverflow();
        }

        return $options;
    }

    public static function label(CarbonImmutable $period): string
    {
        return Str::ucfirst($period->locale('es')->translatedFormat('F Y'));
    }

    public static function find(FiscalBook $book, CarbonImmutable $period): ?FiscalBookPeriod
    {
        return FiscalBookPeriod::query()
            ->where('book', $book->value)
            ->where('period', $period->startOfMonth()->toDateString())
            ->first();
    }

    public static function isClosed(FiscalBook $book, CarbonImmutable $period): bool
    {
        return (bool) self::find($book, $period)?->isClosed();
    }

    public static function assertOpen(FiscalBook $book, CarbonImmutable $period): void
    {
        if (self::isClosed($book, $period)) {
            throw new InvalidArgumentException('El '.mb_strtolower($book->label()).' de '.mb_strtolower(self::label($period)).' está cerrado. Para modificarlo, reábralo primero con una razón.');
        }
    }

    public static function close(FiscalBook $book, CarbonImmutable $period, User $user): FiscalBookPeriod
    {
        $period = $period->startOfMonth();

        if ($period->gt(self::current())) {
            throw new InvalidArgumentException('No se puede cerrar un mes que aún no empieza.');
        }

        return DB::transaction(function () use ($book, $period, $user): FiscalBookPeriod {
            $record = self::lockedRecord($book, $period);

            if ($record->isClosed()) {
                throw new InvalidArgumentException('Ese mes ya está cerrado.');
            }

            $totals = FiscalBookLedger::totals($book, $period);

            if ($totals['documents'] === 0) {
                throw new InvalidArgumentException('El libro de '.mb_strtolower(self::label($period)).' no tiene documentos: no hay nada que cerrar.');
            }

            $record->forceFill([
                'status' => FiscalBookPeriod::STATUS_CLOSED,
                'totals' => $totals,
                'closed_at' => now(),
                'closed_by_id' => $user->getKey(),
                'closed_by_name' => Str::limit((string) $user->name, 150, ''),
            ])->save();

            self::logEvent($record, FiscalBookPeriodEvent::ACTION_CLOSED, null, $totals, $user);

            SecurityAudit::log('AUDIT_FISCAL_BOOK_CLOSED', 'administration.fiscal-books.close', [
                'book' => $book->value,
                'period' => $period->toDateString(),
                'totals' => $totals,
            ], $user);

            return $record->refresh();
        });
    }

    public static function reopen(FiscalBook $book, CarbonImmutable $period, string $reason, User $user): FiscalBookPeriod
    {
        $reason = Str::squish($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('Indique la razón para reabrir el mes (mínimo '.self::MIN_REASON_LENGTH.' caracteres).');
        }

        $period = $period->startOfMonth();

        return DB::transaction(function () use ($book, $period, $reason, $user): FiscalBookPeriod {
            $record = self::lockedRecord($book, $period);

            if (! $record->isClosed()) {
                throw new InvalidArgumentException('Ese mes no está cerrado.');
            }

            $record->forceFill([
                'status' => FiscalBookPeriod::STATUS_OPEN,
                'reopened_at' => now(),
                'reopened_by_id' => $user->getKey(),
                'reopened_by_name' => Str::limit((string) $user->name, 150, ''),
                'reopen_reason' => Str::limit($reason, 1000, ''),
            ])->save();

            self::logEvent($record, FiscalBookPeriodEvent::ACTION_REOPENED, $reason, $record->totals, $user);

            SecurityAudit::log('AUDIT_FISCAL_BOOK_REOPENED', 'administration.fiscal-books.reopen', [
                'book' => $book->value,
                'period' => $period->toDateString(),
                'reason' => $reason,
            ], $user);

            return $record->refresh();
        });
    }

    /**
     * Bloquea el mes para escribir en él (llamar dentro de una transacción). Así
     * nadie lo cierra mientras se guarda una línea, y dos analistas no cargan la
     * misma factura a la vez: la segunda espera y ve la primera como duplicada.
     */
    public static function lockOpen(FiscalBook $book, CarbonImmutable $period): void
    {
        $record = self::lockedRecord($book, $period->startOfMonth());

        if ($record->isClosed()) {
            throw new InvalidArgumentException('El '.mb_strtolower($book->label()).' de '.mb_strtolower(self::label($period)).' está cerrado. Para modificarlo, reábralo primero con una razón.');
        }
    }

    /**
     * Fila del mes bloqueada para escribir; la crea abierta si no existe.
     */
    private static function lockedRecord(FiscalBook $book, CarbonImmutable $period): FiscalBookPeriod
    {
        FiscalBookPeriod::query()->insertOrIgnore([
            'book' => $book->value,
            'period' => $period->toDateString(),
            'status' => FiscalBookPeriod::STATUS_OPEN,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return FiscalBookPeriod::query()
            ->where('book', $book->value)
            ->where('period', $period->toDateString())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>|null  $totals
     */
    private static function logEvent(FiscalBookPeriod $record, string $action, ?string $reason, ?array $totals, User $user): void
    {
        FiscalBookPeriodEvent::query()->create([
            'fiscal_book_period_id' => $record->getKey(),
            'action' => $action,
            'reason' => $reason,
            'totals' => $totals,
            'user_id' => $user->getKey(),
            'user_name' => Str::limit((string) $user->name, 150, ''),
        ]);
    }
}
