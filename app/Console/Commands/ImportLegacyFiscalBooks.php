<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\FiscalBook;
use App\Models\FiscalBookEntry;
use App\Models\User;
use App\Support\Administration\FiscalBooks\FiscalAmount;
use App\Support\Administration\FiscalBooks\FiscalBookEntryWriter;
use App\Support\Administration\FiscalBooks\FiscalBookLedger;
use App\Support\Administration\FiscalBooks\FiscalBookPeriods;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Carga el histórico de los libros de Ventas y Compras que Administración llevaba
 * en Excel (convertido a JSON: una hoja por mes, sus filas y su total).
 *
 * Idempotente: una fila ya importada (mismo libro, mes y N° de operación) se salta.
 * Al final compara los totales de cada mes contra el total del Excel.
 */
class ImportLegacyFiscalBooks extends Command
{
    protected $signature = 'fiscal-books:import-legacy
        {file : Ruta del JSON con las hojas del libro}
        {--close : Cerrar cada mes importado (ya declarados)}
        {--close-until= : Cerrar solo hasta este mes inclusive (AAAA-MM)}
        {--user= : Correo del usuario que queda como autor del cierre}
        {--dry-run : Validar sin guardar}';

    protected $description = 'Importa el histórico del Libro de Ventas o de Compras desde el Excel convertido a JSON';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error('No existe el archivo '.$path);

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        $book = FiscalBook::tryFrom((string) ($payload['book'] ?? ''));

        if (! is_array($payload) || $book === null) {
            $this->error('El archivo no indica un libro válido (VENTAS o COMPRAS).');

            return self::FAILURE;
        }

        $user = null;

        if ($this->option('close')) {
            $user = User::query()->where('email', (string) $this->option('user'))->first();

            if ($user === null) {
                $this->error('Para cerrar los meses indique --user con el correo de un usuario existente.');

                return self::FAILURE;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $problems = [];
        $summary = [];

        foreach ((array) ($payload['sheets'] ?? []) as $sheet) {
            $period = FiscalBookPeriods::parse((string) ($sheet['period'] ?? ''));
            $created = 0;
            $skipped = 0;

            foreach ((array) ($sheet['rows'] ?? []) as $row) {
                $position = (int) ($row['position'] ?? 0);
                $label = $period->format('Y-m').' fila '.$position.' (N° '.($row['number'] ?? '?').')';

                $exists = FiscalBookEntry::query()
                    ->forBook($book)
                    ->where('source', FiscalBookEntry::SOURCE_LEGACY)
                    ->where('period', $period->toDateString())
                    ->where('position', $position)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                try {
                    [$input, $notes] = self::input($book, $period, $row);

                    foreach ($notes as $note) {
                        $problems[] = $label.': '.$note;
                    }

                    if ($dryRun) {
                        if ($input !== null) {
                            FiscalBookEntryWriter::normalize($book, $input, allowZero: true);
                        }
                        $created++;

                        continue;
                    }

                    $origin = ['source' => FiscalBookEntry::SOURCE_LEGACY, 'position' => $position];
                    $repeated = self::repeatedIn($book, (string) ($row['number'] ?? ''), $input['counterparty_rif'] ?? null);

                    if ($repeated !== null && $input !== null) {
                        $note = 'número repetido en el Excel: también está en '.$repeated;
                        $problems[] = $label.': '.$note;
                        $input['notes'] = trim(($input['notes'] ?? 'Importada del Excel:').' '.$note.'.');
                    }

                    $input === null
                        ? FiscalBookEntryWriter::createAnnulled($book, self::annulledInput($period, $row), 'Anulada en el libro de Excel', null, $origin)
                        : FiscalBookEntryWriter::create($book, $input, null, $origin, audit: false);

                    $created++;
                } catch (InvalidArgumentException $exception) {
                    $problems[] = $label.': NO IMPORTADA · '.$exception->getMessage();
                }
            }

            $summary[] = $this->compare($book, $period, $sheet, $created, $skipped, $dryRun);

            $closeUntil = $this->option('close-until');
            $shouldClose = ! is_string($closeUntil) || $closeUntil === '' || $period->format('Y-m') <= $closeUntil;

            if ($user !== null && $shouldClose && ! $dryRun && ! FiscalBookPeriods::isClosed($book, $period)) {
                FiscalBookPeriods::close($book, $period, $user);
            }
        }

        $this->table(['Mes', 'Importadas', 'Ya estaban', 'Total sistema', 'Total Excel', 'Diferencia'], $summary);

        if ($problems !== []) {
            $this->warn(count($problems).' observaciones:');

            foreach ($problems as $problem) {
                $this->line(' · '.$problem);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Fila del Excel → entrada del libro. Devuelve null si es una anulada.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: array<string, mixed>|null, 1: list<string>}
     */
    public static function input(FiscalBook $book, CarbonImmutable $period, array $row): array
    {
        $notes = filled($row['date_note'] ?? null) ? [(string) $row['date_note']] : [];
        $name = trim((string) ($row['name'] ?? ''));
        $total = FiscalAmount::of($row['total'] ?? '0');

        if (preg_match('/^ANULAD[OA]$/iu', $name) === 1 && FiscalAmount::isZero($total)) {
            return [null, $notes];
        }

        $number = trim((string) ($row['number'] ?? ''));

        if ($number === '') {
            $number = 'S/N';
            $notes[] = 'sin número de factura en el Excel: se importó como «S/N»';
        }

        if (FiscalAmount::isZero($total)) {
            $notes[] = 'montos en cero en el Excel sin marca de anulada: se importó igual, revísela';
        }

        $exempt = FiscalAmount::of($row['exempt'] ?? '0');
        $export = FiscalAmount::of($row['export'] ?? '0');
        $ncBase = FiscalAmount::of($row['nc_base'] ?? '0');
        $nonTaxpayer = ! FiscalAmount::isZero($ncBase) || mb_strtoupper(trim((string) ($row['contributor'] ?? ''))) === 'NC';
        $base = $nonTaxpayer ? $ncBase : FiscalAmount::of($row['base'] ?? '0');
        $rate = self::rate($row['rate'] ?? null);

        if (! FiscalAmount::isZero($base)) {
            $tax = FiscalAmount::sub(FiscalAmount::sub(FiscalAmount::sub($total, $exempt), $export), $base);
        } else {
            $tax = FiscalAmount::ZERO;
            $rest = FiscalAmount::sub(FiscalAmount::sub($total, $exempt), $export);

            if (! FiscalAmount::isZero($rest)) {
                $notes[] = 'el total ('.FiscalAmount::format($total).') no era exento + exportación; se tomó el total como exento';
                $exempt = FiscalAmount::sub($total, $export);
            }
        }

        return [[
            'period' => $period->format('Y-m'),
            'document_date' => $row['date'] ?? null,
            'document_type' => self::type((string) ($row['type'] ?? '')),
            'document_number' => $number,
            'control_number' => $row['control'] ?? null,
            'series' => $row['series'] ?? null,
            'notes' => $notes === [] ? null : 'Importada del Excel: '.implode('; ', $notes).'.',
            'affected_document_number' => self::type((string) ($row['type'] ?? '')) === FiscalBookEntry::TYPE_INVOICE ? null : ($row['number'] ?? null),
            'counterparty_name' => $name,
            'counterparty_rif' => $row['rif'] ?? null,
            'taxpayer_type' => $nonTaxpayer ? FiscalBookEntry::NON_TAXPAYER : FiscalBookEntry::TAXPAYER,
            'exempt_amount' => FiscalAmount::abs($exempt),
            'export_amount' => FiscalAmount::abs($export),
            'taxable_base' => FiscalAmount::abs($base),
            'tax_rate' => $rate,
            'tax_amount' => FiscalAmount::abs($tax),
            'withholding_date' => ($row['ret_date'] ?? '') !== '' ? $row['ret_date'] : null,
            'withholding_number' => ($row['ret_number'] ?? '') !== '' ? $row['ret_number'] : null,
            'withholding_amount' => FiscalAmount::abs($row['ret_amount'] ?? '0'),
        ], $notes];
    }

    /**
     * Mes y nombre de otra línea del libro con el mismo número (en compras, del mismo proveedor).
     */
    private static function repeatedIn(FiscalBook $book, string $number, ?string $rif): ?string
    {
        if (trim($number) === '') {
            return null;
        }

        $existing = FiscalBookEntry::query()
            ->forBook($book)
            ->where('document_number_key', FiscalBookEntry::documentNumberKey($number))
            ->when($book === FiscalBook::Purchases, fn ($query) => $query->where('counterparty_rif', FiscalBookEntryWriter::rif($rif)))
            ->first(['period', 'counterparty_name']);

        return $existing === null ? null : mb_strtolower(FiscalBookPeriods::label(CarbonImmutable::parse($existing->period))).' ('.$existing->counterparty_name.')';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function annulledInput(CarbonImmutable $period, array $row): array
    {
        return [
            'notes' => filled($row['date_note'] ?? null) ? 'Importada del Excel: '.$row['date_note'].'.' : null,
            'period' => $period->format('Y-m'),
            'document_date' => $row['date'] ?? null,
            'document_number' => $row['number'] ?? null,
            'control_number' => $row['control'] ?? null,
            'series' => $row['series'] ?? null,
        ];
    }

    private static function type(string $type): string
    {
        $type = mb_strtoupper(trim($type));

        return match (true) {
            str_contains($type, 'CR') || $type === 'NC' => FiscalBookEntry::TYPE_CREDIT_NOTE,
            str_contains($type, 'DEB') || str_contains($type, 'DÉB') || $type === 'ND' => FiscalBookEntry::TYPE_DEBIT_NOTE,
            default => FiscalBookEntry::TYPE_INVOICE,
        };
    }

    /**
     * El Excel guarda la alícuota como fracción (0.16).
     */
    private static function rate(mixed $value): string
    {
        $rate = FiscalAmount::of($value ?? '0');

        if (FiscalAmount::isZero($rate)) {
            return FiscalBookLedger::RATE_GENERAL;
        }

        return FiscalAmount::compare($rate, '1') < 0 ? FiscalAmount::of(bcmul($rate, '100', 2)) : $rate;
    }

    /**
     * @param  array<string, mixed>  $sheet
     * @return array<int, string|int>
     */
    private function compare(FiscalBook $book, CarbonImmutable $period, array $sheet, int $created, int $skipped, bool $dryRun): array
    {
        $expected = is_array($sheet['expected'] ?? null) ? FiscalAmount::of($sheet['expected']['total'] ?? '0') : null;
        $system = $dryRun ? null : FiscalBookLedger::totals($book, $period)['total'];

        return [
            $period->format('Y-m'),
            $created,
            $skipped,
            $system === null ? '—' : FiscalAmount::format($system),
            $expected === null ? '—' : FiscalAmount::format($expected),
            ($system === null || $expected === null) ? '—' : FiscalAmount::format(FiscalAmount::sub($system, $expected)),
        ];
    }
}
