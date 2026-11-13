<?php

declare(strict_types=1);

namespace App\Support\Administration\FiscalBooks;

use App\Enums\FiscalBook;
use App\Models\FiscalBookEntry;
use App\Models\User;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Único punto de escritura de las líneas de los libros fiscales.
 *
 * Garantías:
 * - total = exento + exportación + base + IVA, exacto al céntimo (bcmath).
 * - IVA = base × alícuota redondeado; se acepta el IVA impreso en la factura si
 *   difiere a lo sumo {@see TAX_TOLERANCE} por redondeo del emisor.
 * - Notas de crédito con montos negativos; anuladas en cero.
 * - Sin duplicados: ventas por número (y serie); compras por número y RIF del proveedor.
 * - Nada se escribe en un mes cerrado, y el mes queda bloqueado mientras se guarda.
 */
final class FiscalBookEntryWriter
{
    public const TAX_TOLERANCE = '0.05';

    /** Campos editables desde el formulario. */
    public const FIELDS = [
        'period', 'document_date', 'document_type', 'document_number', 'control_number', 'series',
        'affected_document_number', 'counterparty_name', 'counterparty_rif', 'taxpayer_type',
        'exempt_amount', 'export_amount', 'taxable_base', 'tax_rate', 'tax_amount',
        'withholding_date', 'withholding_number', 'withholding_amount', 'notes',
    ];

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $origin  source, source_id, source_amount_usd, exchange_rate, position
     */
    public static function create(FiscalBook $book, array $input, ?User $user, array $origin = [], bool $audit = true): FiscalBookEntry
    {
        $attributes = self::normalize($book, $input, allowZero: ($origin['source'] ?? null) === FiscalBookEntry::SOURCE_LEGACY);
        $period = CarbonImmutable::parse($attributes['period']);

        return DB::transaction(function () use ($book, $attributes, $period, $user, $origin, $audit): FiscalBookEntry {
            FiscalBookPeriods::lockOpen($book, $period);

            // El histórico se importa tal como se declaró, aun con números repetidos (queda nota).
            if (($origin['source'] ?? null) !== FiscalBookEntry::SOURCE_LEGACY) {
                self::assertNotDuplicated($book, $attributes, null);
            }

            $entry = new FiscalBookEntry;
            $entry->forceFill([
                ...$attributes,
                'book' => $book,
                'source' => $origin['source'] ?? FiscalBookEntry::SOURCE_MANUAL,
                'source_id' => $origin['source_id'] ?? null,
                'source_amount_usd' => $origin['source_amount_usd'] ?? null,
                'exchange_rate' => $origin['exchange_rate'] ?? null,
                'position' => $origin['position'] ?? null,
                'created_by_id' => $user?->getKey(),
                'created_by_name' => self::userName($user),
                'updated_by_id' => $user?->getKey(),
                'updated_by_name' => self::userName($user),
            ])->save();

            if ($audit) {
                self::audit('AUDIT_FISCAL_BOOK_ENTRY_CREATED', 'create', $entry, $user);
            }

            FiscalBookLedger::flush();

            return $entry;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function update(FiscalBookEntry $entry, array $input, ?User $user, bool $fromSource = false): FiscalBookEntry
    {
        $book = $entry->book;
        $current = self::currentInput($entry);

        // Si cambia la base o la alícuota y no se indica el IVA, el IVA se recalcula.
        if (! array_key_exists('tax_amount', $input) && (array_key_exists('taxable_base', $input) || array_key_exists('tax_rate', $input))) {
            unset($current['tax_amount']);
        }

        $attributes = self::normalize($book, [...$current, ...$input]);

        return DB::transaction(function () use ($entry, $book, $attributes, $user, $fromSource): FiscalBookEntry {
            $locked = FiscalBookEntry::query()->lockForUpdate()->findOrFail($entry->getKey());

            FiscalBookPeriods::lockOpen($book, CarbonImmutable::parse($locked->period));
            FiscalBookPeriods::lockOpen($book, CarbonImmutable::parse($attributes['period']));
            self::assertNotDuplicated($book, $attributes, (int) $locked->getKey());

            if ($locked->isAnnulled()) {
                throw new InvalidArgumentException('Una factura anulada no se edita.');
            }

            $before = $locked->only(['document_number', 'counterparty_name', 'total_amount', 'taxable_base', 'tax_amount', 'period']);

            $locked->forceFill([
                ...$attributes,
                'manually_adjusted' => $fromSource ? $locked->manually_adjusted : ($locked->manually_adjusted || $locked->source === FiscalBookEntry::SOURCE_SALE),
                'updated_by_id' => $user?->getKey(),
                'updated_by_name' => self::userName($user),
            ])->save();

            self::audit('AUDIT_FISCAL_BOOK_ENTRY_UPDATED', 'update', $locked, $user, ['before' => $before]);
            FiscalBookLedger::flush();

            return $locked;
        });
    }

    /**
     * Anula el documento: conserva número, fecha y emisor (la numeración no
     * puede tener huecos) y deja los montos en cero.
     */
    public static function annul(FiscalBookEntry $entry, string $reason, ?User $user): FiscalBookEntry
    {
        $reason = Str::squish($reason);

        if (mb_strlen($reason) < FiscalBookPeriods::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('Indique la razón de la anulación (mínimo '.FiscalBookPeriods::MIN_REASON_LENGTH.' caracteres).');
        }

        return DB::transaction(function () use ($entry, $reason, $user): FiscalBookEntry {
            $locked = FiscalBookEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
            FiscalBookPeriods::lockOpen($locked->book, CarbonImmutable::parse($locked->period));

            if ($locked->isAnnulled()) {
                throw new InvalidArgumentException('Ese documento ya está anulado.');
            }

            $before = $locked->only(['total_amount', 'taxable_base', 'tax_amount', 'counterparty_name']);

            $locked->forceFill([
                ...self::zeroAmounts(),
                'status' => FiscalBookEntry::STATUS_ANNULLED,
                'annulment_reason' => Str::limit($reason, 500, ''),
                'manually_adjusted' => true,
                'updated_by_id' => $user?->getKey(),
                'updated_by_name' => self::userName($user),
            ])->save();

            self::audit('AUDIT_FISCAL_BOOK_ENTRY_ANNULLED', 'annul', $locked, $user, ['before' => $before, 'reason' => $reason]);
            FiscalBookLedger::flush();

            return $locked;
        });
    }

    /**
     * Quita del libro una línea cargada a mano o importada de cuentas por pagar
     * (eliminación lógica). Las de Ventas del sistema y del Excel histórico se
     * anulan, no se eliminan.
     */
    public static function delete(FiscalBookEntry $entry, ?User $user): void
    {
        if (! self::canDelete($entry)) {
            throw new InvalidArgumentException('Esta línea viene de '.mb_strtolower(FiscalBookEntry::sourceOptions()[$entry->source] ?? $entry->source).': anúlela en lugar de eliminarla.');
        }

        DB::transaction(function () use ($entry, $user): void {
            $locked = FiscalBookEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
            FiscalBookPeriods::lockOpen($locked->book, CarbonImmutable::parse($locked->period));

            $locked->forceFill([
                'deleted_by_id' => $user?->getKey(),
                'deleted_by_name' => self::userName($user),
            ])->save();
            $locked->delete();

            self::audit('AUDIT_FISCAL_BOOK_ENTRY_DELETED', 'delete', $locked, $user);
            FiscalBookLedger::flush();
        });
    }

    public static function canDelete(FiscalBookEntry $entry): bool
    {
        return in_array($entry->source, [FiscalBookEntry::SOURCE_MANUAL, FiscalBookEntry::SOURCE_PAYABLE], true);
    }

    /**
     * Valida y calcula. Recibe montos positivos; la nota de crédito los vuelve negativos.
     *
     * El histórico en Excel trae documentos en cero sin marca de anulado: solo
     * esa importación los acepta (`$allowZero`), para que el libro quede idéntico.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(FiscalBook $book, array $input, bool $allowZero = false): array
    {
        $documentDate = self::date($input['document_date'] ?? null, 'la fecha del documento');
        $period = FiscalBookPeriods::parse(is_string($input['period'] ?? null) ? $input['period'] : $documentDate->format('Y-m'));

        if ($book === FiscalBook::Sales && $documentDate->format('Y-m') !== $period->format('Y-m')) {
            throw new InvalidArgumentException('La fecha de la factura de venta debe estar dentro de '.mb_strtolower(FiscalBookPeriods::label($period)).'.');
        }

        if ($book === FiscalBook::Purchases && $documentDate->gt($period->endOfMonth())) {
            throw new InvalidArgumentException('Una factura de compra fechada después de '.mb_strtolower(FiscalBookPeriods::label($period)).' no puede ir en ese libro.');
        }

        $type = (string) ($input['document_type'] ?? FiscalBookEntry::TYPE_INVOICE);

        if (! array_key_exists($type, FiscalBookEntry::documentTypeOptions())) {
            throw new InvalidArgumentException('El tipo de documento no es válido.');
        }

        $number = self::text($input['document_number'] ?? null, 40);

        if ($number === null) {
            throw new InvalidArgumentException('Indique el número del documento.');
        }

        $name = self::text($input['counterparty_name'] ?? null, 255);

        if ($name === null) {
            throw new InvalidArgumentException('Indique el nombre o razón social del '.mb_strtolower($book->counterpartyLabel()).'.');
        }

        $affected = self::text($input['affected_document_number'] ?? null, 40);

        if ($type !== FiscalBookEntry::TYPE_INVOICE && $affected === null) {
            throw new InvalidArgumentException('Indique el número de la factura afectada por la nota.');
        }

        $taxpayer = (string) ($input['taxpayer_type'] ?? FiscalBookEntry::TAXPAYER);

        if (! in_array($taxpayer, [FiscalBookEntry::TAXPAYER, FiscalBookEntry::NON_TAXPAYER], true)) {
            throw new InvalidArgumentException('El tipo de contribuyente debe ser C o NC.');
        }

        $rate = FiscalAmount::of($input['tax_rate'] ?? FiscalBookLedger::RATE_GENERAL);

        if (FiscalAmount::isNegative($rate) || FiscalAmount::compare($rate, '100') > 0) {
            throw new InvalidArgumentException('La alícuota no es válida.');
        }

        $exempt = self::positive($input['exempt_amount'] ?? null, 'el monto exento');
        $export = self::positive($input['export_amount'] ?? null, 'el monto de exportación');
        $base = self::positive($input['taxable_base'] ?? null, 'la base imponible');
        $expectedTax = FiscalAmount::taxFromBase($base, $rate);
        $rawTax = $input['tax_amount'] ?? null;
        $tax = ($rawTax === null || $rawTax === '') ? $expectedTax : self::positive($rawTax, 'el IVA');

        if (FiscalAmount::compare(FiscalAmount::abs(FiscalAmount::sub($tax, $expectedTax)), self::TAX_TOLERANCE) > 0) {
            throw new InvalidArgumentException('El IVA ('.FiscalAmount::format($tax).') no corresponde a la base: el '.FiscalAmount::format($rate).' % de '.FiscalAmount::format($base).' es '.FiscalAmount::format($expectedTax).'.');
        }

        $total = FiscalAmount::add($exempt, $export, $base, $tax);

        if (FiscalAmount::isZero($total) && ! $allowZero) {
            throw new InvalidArgumentException('El documento no tiene montos. Si fue anulado, regístrelo y luego use «Anular».');
        }

        $withholding = self::positive($input['withholding_amount'] ?? null, 'el monto retenido');
        $withholdingNumber = self::text($input['withholding_number'] ?? null, 40);
        $withholdingDate = filled($input['withholding_date'] ?? null) ? self::date($input['withholding_date'], 'la fecha del comprobante de retención') : null;

        if (! FiscalAmount::isZero($withholding) && ($withholdingNumber === null || $withholdingDate === null)) {
            throw new InvalidArgumentException('Una retención necesita su número de comprobante y su fecha.');
        }

        if (FiscalAmount::compare($withholding, $tax) > 0) {
            throw new InvalidArgumentException('La retención no puede ser mayor que el IVA del documento.');
        }

        $sign = $type === FiscalBookEntry::TYPE_CREDIT_NOTE ? -1 : 1;
        $signed = fn (string $amount): string => $sign === -1 ? FiscalAmount::negate($amount) : $amount;

        return [
            'period' => $period->toDateString(),
            'document_date' => $documentDate->toDateString(),
            'document_type' => $type,
            'document_number' => $number,
            'document_number_key' => FiscalBookEntry::documentNumberKey($number),
            'control_number' => self::text($input['control_number'] ?? null, 40),
            'series' => self::text($input['series'] ?? null, 20),
            'affected_document_number' => $type === FiscalBookEntry::TYPE_INVOICE ? null : $affected,
            'counterparty_name' => mb_strtoupper($name),
            'counterparty_rif' => self::rif($input['counterparty_rif'] ?? null),
            'taxpayer_type' => $taxpayer,
            'status' => FiscalBookEntry::STATUS_ACTIVE,
            'total_amount' => $signed($total),
            'exempt_amount' => $signed($exempt),
            'export_amount' => $signed($export),
            'taxable_base' => $signed($base),
            'tax_rate' => $rate,
            'tax_amount' => $signed($tax),
            'withholding_date' => $withholdingDate?->toDateString(),
            'withholding_number' => $withholdingNumber,
            'withholding_amount' => $signed($withholding),
            'notes' => self::text($input['notes'] ?? null, 500),
        ];
    }

    /**
     * Estado actual en forma de entrada del formulario (montos en positivo).
     *
     * @return array<string, mixed>
     */
    public static function currentInput(FiscalBookEntry $entry): array
    {
        return [
            'period' => $entry->period?->format('Y-m'),
            'document_date' => $entry->document_date?->toDateString(),
            'document_type' => $entry->document_type,
            'document_number' => $entry->document_number,
            'control_number' => $entry->control_number,
            'series' => $entry->series,
            'affected_document_number' => $entry->affected_document_number,
            'counterparty_name' => $entry->counterparty_name,
            'counterparty_rif' => $entry->counterparty_rif,
            'taxpayer_type' => $entry->taxpayer_type,
            'exempt_amount' => FiscalAmount::abs($entry->exempt_amount),
            'export_amount' => FiscalAmount::abs($entry->export_amount),
            'taxable_base' => FiscalAmount::abs($entry->taxable_base),
            'tax_rate' => FiscalAmount::of($entry->tax_rate),
            'tax_amount' => FiscalAmount::abs($entry->tax_amount),
            'withholding_date' => $entry->withholding_date?->toDateString(),
            'withholding_number' => $entry->withholding_number,
            'withholding_amount' => FiscalAmount::abs($entry->withholding_amount),
            'notes' => $entry->notes,
        ];
    }

    /**
     * Documento anulado ya emitido (por ejemplo, del Excel histórico): se registra en cero.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $origin
     */
    public static function createAnnulled(FiscalBook $book, array $input, string $reason, ?User $user, array $origin = []): FiscalBookEntry
    {
        $documentDate = self::date($input['document_date'] ?? null, 'la fecha del documento');
        $period = FiscalBookPeriods::parse(is_string($input['period'] ?? null) ? $input['period'] : $documentDate->format('Y-m'));
        $number = self::text($input['document_number'] ?? null, 40) ?? throw new InvalidArgumentException('Indique el número del documento.');

        $attributes = [
            'period' => $period->toDateString(),
            'document_date' => $documentDate->toDateString(),
            'document_type' => FiscalBookEntry::TYPE_INVOICE,
            'document_number' => $number,
            'document_number_key' => FiscalBookEntry::documentNumberKey($number),
            'control_number' => self::text($input['control_number'] ?? null, 40),
            'series' => self::text($input['series'] ?? null, 20),
            'counterparty_name' => 'ANULADA',
            'counterparty_rif' => null,
            'taxpayer_type' => FiscalBookEntry::TAXPAYER,
            'status' => FiscalBookEntry::STATUS_ANNULLED,
            'annulment_reason' => Str::limit(Str::squish($reason), 500, ''),
            'notes' => self::text($input['notes'] ?? null, 500),
            'tax_rate' => FiscalBookLedger::RATE_GENERAL,
            ...self::zeroAmounts(),
        ];

        return DB::transaction(function () use ($book, $attributes, $period, $user, $origin): FiscalBookEntry {
            FiscalBookPeriods::lockOpen($book, $period);
            self::assertNotDuplicated($book, $attributes, null);

            $entry = new FiscalBookEntry;
            $entry->forceFill([
                ...$attributes,
                'book' => $book,
                'source' => $origin['source'] ?? FiscalBookEntry::SOURCE_MANUAL,
                'position' => $origin['position'] ?? null,
                'created_by_id' => $user?->getKey(),
                'created_by_name' => self::userName($user),
            ])->save();

            FiscalBookLedger::flush();

            return $entry;
        });
    }

    /**
     * «v 6431109», «J502344691», «V- 204302657» → «V-6431109», «J-502344691», «V-204302657».
     */
    public static function rif(mixed $value): ?string
    {
        $clean = mb_strtoupper(preg_replace('/[\s.]+/', '', (string) ($value ?? '')) ?? '');

        if ($clean === '') {
            return null;
        }

        if (preg_match('/^([VEJGPC])-?(\d+(?:-\d)?)$/', $clean, $matches) === 1) {
            return $matches[1].'-'.$matches[2];
        }

        return Str::limit($clean, 30, '');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function assertNotDuplicated(FiscalBook $book, array $attributes, ?int $exceptId): void
    {
        $query = FiscalBookEntry::query()
            ->forBook($book)
            ->where('document_number_key', $attributes['document_number_key'])
            ->where('document_type', $attributes['document_type'])
            ->when($exceptId !== null, fn ($builder) => $builder->whereKeyNot($exceptId));

        if ($book === FiscalBook::Sales) {
            $query->where(function ($builder) use ($attributes): void {
                $attributes['series'] === null
                    ? $builder->whereNull('series')
                    : $builder->where('series', $attributes['series']);
            });
        } else {
            $query->where(function ($builder) use ($attributes): void {
                $attributes['counterparty_rif'] === null
                    ? $builder->whereNull('counterparty_rif')
                    : $builder->where('counterparty_rif', $attributes['counterparty_rif']);
            });
        }

        $duplicate = $query->first(['id', 'period', 'counterparty_name']);

        if ($duplicate !== null) {
            $label = mb_strtolower(FiscalBookPeriods::label(CarbonImmutable::parse($duplicate->period)));

            throw new InvalidArgumentException($book === FiscalBook::Sales
                ? 'La factura N° '.$attributes['document_number'].' ya está en el libro de ventas de '.$label.' ('.$duplicate->counterparty_name.').'
                : 'La factura N° '.$attributes['document_number'].' de '.$duplicate->counterparty_name.' ya está en el libro de compras de '.$label.'.');
        }
    }

    /**
     * @return array<string, string|null>
     */
    private static function zeroAmounts(): array
    {
        return [
            'total_amount' => FiscalAmount::ZERO,
            'exempt_amount' => FiscalAmount::ZERO,
            'export_amount' => FiscalAmount::ZERO,
            'taxable_base' => FiscalAmount::ZERO,
            'tax_amount' => FiscalAmount::ZERO,
            'withholding_amount' => FiscalAmount::ZERO,
            'withholding_date' => null,
            'withholding_number' => null,
        ];
    }

    private static function positive(mixed $value, string $label): string
    {
        try {
            $amount = FiscalAmount::of($value);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('Revise '.$label.': no es un monto válido.');
        }

        if (FiscalAmount::isNegative($amount)) {
            throw new InvalidArgumentException('Indique '.$label.' en positivo: el sistema resta solo las notas de crédito.');
        }

        if (FiscalAmount::compare($amount, '9999999999999999.99') > 0) {
            throw new InvalidArgumentException('Revise '.$label.': el monto es demasiado grande.');
        }

        return $amount;
    }

    private static function date(mixed $value, string $label): CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Indique '.$label.'.');
        }

        try {
            $date = preg_match('/^\d{2}\/\d{2}\/\d{4}$/', trim($value)) === 1
                ? CarbonImmutable::createFromFormat('d/m/Y', trim($value))
                : CarbonImmutable::parse(trim($value));
        } catch (Throwable) {
            throw new InvalidArgumentException('Revise '.$label.': no es una fecha válida.');
        }

        if ($date === null || $date === false || $date->year < 2000 || $date->gt(CarbonImmutable::now()->addYear())) {
            throw new InvalidArgumentException('Revise '.$label.': no es una fecha válida.');
        }

        return $date->startOfDay();
    }

    private static function text(mixed $value, int $max): ?string
    {
        $text = Str::squish((string) ($value ?? ''));

        return $text === '' ? null : Str::limit($text, $max, '');
    }

    private static function userName(?User $user): ?string
    {
        return $user === null ? null : Str::limit((string) $user->name, 150, '');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private static function audit(string $event, string $action, FiscalBookEntry $entry, ?User $user, array $extra = []): void
    {
        SecurityAudit::log($event, 'administration.fiscal-books.'.$action, [
            'entry_id' => $entry->getKey(),
            'book' => $entry->book->value,
            'period' => $entry->period?->toDateString(),
            'document_number' => $entry->document_number,
            'counterparty' => $entry->counterparty_name,
            'total_amount' => $entry->total_amount,
            'tax_amount' => $entry->tax_amount,
            ...$extra,
        ], $user);
    }
}
