<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Registra el comprobante de pago y los datos bancarios de una o varias
 * cuentas por pagar, en una sola transacción.
 */
final class AccountsPayablePaymentReceiptRegistrar
{
    public const DISK = 'public';

    public const DIRECTORY = 'operation-accounts-payables/payment-receipts';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function apply(OperationAccountsPayable $record, array $data, string $actor): OperationAccountsPayable
    {
        self::applyMany(collect([$record]), $data, $actor);

        return $record->refresh();
    }

    /**
     * @param  Collection<int, OperationAccountsPayable>  $records
     * @param  array<string, mixed>  $data
     * @return array{updated: int, receipt_path: string}
     */
    public static function applyMany(Collection $records, array $data, string $actor): array
    {
        if ($records->isEmpty()) {
            throw new InvalidArgumentException('Selecciona al menos una factura para registrar el comprobante.');
        }

        $path = self::normalizePath($data['payment_receipt_path'] ?? null);

        if ($path === null) {
            $path = self::sharedExistingReceiptPath($records);
        }

        if ($path === null) {
            throw new InvalidArgumentException('Adjunta el comprobante de pago (imagen o PDF).');
        }

        $reference = trim((string) ($data['payment_reference'] ?? ''));

        if ($reference === '') {
            throw new InvalidArgumentException('Indica la referencia de pago del comprobante.');
        }

        $paymentDate = self::parseDate($data['payment_date'] ?? null);

        if ($paymentDate === null) {
            throw new InvalidArgumentException('Indica la fecha del pago.');
        }

        $formUsd = self::nullableAmount($data['payment_amount_usd'] ?? null);
        $formVes = self::nullableAmount($data['payment_amount_ves'] ?? null);
        $nationalBank = self::nullableString($data['national_bank'] ?? null);
        $internationalBank = self::nullableString($data['international_bank'] ?? null);
        $requestedStatus = StatusCuentaPorPagar::fromStored($data['payment_status'] ?? null)
            ?? StatusCuentaPorPagar::Pagada;

        return DB::transaction(function () use (
            $records,
            $path,
            $reference,
            $paymentDate,
            $formUsd,
            $formVes,
            $nationalBank,
            $internationalBank,
            $requestedStatus,
            $actor,
        ): array {
            $ids = $records
                ->map(fn (OperationAccountsPayable $record): int => (int) $record->getKey())
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();

            $locked = OperationAccountsPayable::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $updated = 0;

            foreach ($ids as $id) {
                /** @var OperationAccountsPayable|null $record */
                $record = $locked->get($id);

                if ($record === null) {
                    continue;
                }

                $previous = self::nullableString($record->payment_receipt_path);
                $status = self::receiptWasNewOrChanged($previous, $path)
                    ? StatusCuentaPorPagar::Pagada
                    : ($requestedStatus === StatusCuentaPorPagar::PendientePorPagar
                        ? StatusCuentaPorPagar::Pagada
                        : $requestedStatus);

                [$usd, $ves] = self::resolveAmounts($record, $formUsd, $formVes);

                $record->fill([
                    'payment_receipt_path' => $path,
                    'payment_status' => $status->value,
                    'payment_reference' => $reference,
                    'payment_date' => $paymentDate,
                    'national_bank' => $nationalBank,
                    'international_bank' => $internationalBank,
                    'payment_amount_usd' => $usd,
                    'payment_amount_ves' => $ves,
                    'updated_by' => $actor,
                ])->save();

                if ($previous !== null && $previous !== $path) {
                    self::deleteIfOrphan($previous, (int) $record->getKey());
                }

                $updated++;
            }

            return [
                'updated' => $updated,
                'receipt_path' => $path,
            ];
        });
    }

    public static function normalizePath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path) ?: null;
        }

        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);

        return $path !== '' ? $path : null;
    }

    /**
     * @param  Collection<int, OperationAccountsPayable>  $records
     */
    public static function sharedExistingReceiptPath(Collection $records): ?string
    {
        $paths = $records
            ->map(fn (OperationAccountsPayable $record): ?string => self::nullableString($record->payment_receipt_path))
            ->filter()
            ->unique()
            ->values();

        return $paths->count() === 1 ? $paths->first() : null;
    }

    public static function receiptWasNewOrChanged(?string $previous, string $next): bool
    {
        $prev = trim((string) ($previous ?? ''));
        $incoming = trim($next);

        return $incoming !== '' && $prev !== $incoming;
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    public static function resolveAmounts(OperationAccountsPayable $record, ?float $formUsd, ?float $formVes): array
    {
        if ($formUsd !== null || $formVes !== null) {
            return [$formUsd, $formVes];
        }

        $invoiceAmount = round((float) $record->invoice_amount, 4);

        if ($record->invoice_currency === 'VES') {
            return [null, $invoiceAmount];
        }

        return [$invoiceAmount, null];
    }

    public static function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return is_string($value) ? $value : null;
        }
    }

    private static function nullableAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 4);
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private static function deleteIfOrphan(string $path, int $exceptId): void
    {
        $stillUsed = OperationAccountsPayable::query()
            ->where('payment_receipt_path', $path)
            ->whereKeyNot($exceptId)
            ->exists();

        if ($stillUsed) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }
}
