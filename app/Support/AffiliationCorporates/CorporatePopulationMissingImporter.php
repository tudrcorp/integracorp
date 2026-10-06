<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\User;
use App\Services\CorporateAffiliatePlanSyncService;
use App\Services\CorporateAffiliateRemovalService;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Agrega a un colectivo las personas de un padrón (Excel/CSV del cliente) que no
 * están en `affiliate_corporates`. Lo usa el comando `affiliations-corporate:add-missing`.
 *
 * Reglas, decididas con Negocios (06/10/2026):
 * - Nadie se crea dos veces: si el documento (solo dígitos) ya está en la afiliación,
 *   la fila se omite; si solo coincide el nombre, se omite y se reporta para revisión
 *   (los menores comparten documento con el representante o tienen uno de relleno),
 *   salvo que se autorice la fila explícitamente.
 * - Sin fecha de nacimiento en el padrón, no se inventa: `birth_date` y `age` quedan
 *   vacíos y el afiliado se suma a mano a la fila de plan (el recálculo automático
 *   solo cuenta afiliados con edad).
 * - Parentesco vacío, como el resto del colectivo; la cédula de menor se guarda tal
 *   cual viene (`023490882-25`), sin perder ceros.
 * - Plan, cobertura y tarifa salen de la única fila de plan del colectivo; si tiene
 *   varias, se aborta: no hay forma segura de elegir.
 */
final class CorporatePopulationMissingImporter
{
    public const ACTION_CREATE = 'crear';

    public const ACTION_EXISTS = 'ya existe (documento)';

    public const ACTION_NAME_MATCH = 'posible duplicado (nombre)';

    public const ACTION_REPEATED = 'repetida en el archivo';

    public const ACTION_INVALID = 'datos incompletos';

    /** Columnas que debe traer el padrón. */
    public const REQUIRED_HEADERS = ['NOMBRE_RIESGO', '1ER_APELLIDO_RIESGO', '2DO_APELLIDO_RIESGO', 'TIP_DOCUMENTO', 'CODIGO_DOCUMENTO'];

    /**
     * Lee el padrón. Las celdas se leen como texto para no perder ceros a la izquierda.
     *
     * @return list<array{row: int, first_name: string, last_name: string, document: string, document_type: string, relationship_code: string}>
     */
    public static function readRows(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('No se encontró el archivo o no se puede leer: '.$path);
        }

        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
        $reader = match ($extension) {
            'xlsx' => new XlsxReader,
            'csv' => new CsvReader,
            default => throw new RuntimeException('Formato no soportado («'.$extension.'»): use .xlsx o .csv.'),
        };

        $reader->open($path);
        $rows = [];
        $headers = null;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    $values = array_map(static fn (mixed $value): string => self::cellToString($value), $row->toArray());

                    if ($headers === null) {
                        $headers = array_map(static fn (string $header): string => Str::upper(trim($header)), $values);
                        $missing = array_diff(self::REQUIRED_HEADERS, $headers);

                        if ($missing !== []) {
                            throw new RuntimeException('Faltan columnas en el padrón: '.implode(', ', $missing).'.');
                        }

                        continue;
                    }

                    $data = [];
                    foreach ($headers as $position => $header) {
                        $data[$header] = $values[$position] ?? '';
                    }

                    if (trim(implode('', $data)) === '') {
                        continue;
                    }

                    $rows[] = [
                        'row' => (int) $index,
                        'first_name' => self::cleanName($data['NOMBRE_RIESGO']),
                        'last_name' => self::cleanName(trim($data['1ER_APELLIDO_RIESGO'].' '.$data['2DO_APELLIDO_RIESGO'])),
                        'document' => trim($data['CODIGO_DOCUMENTO']),
                        'document_type' => Str::upper(trim($data['TIP_DOCUMENTO'])),
                        'relationship_code' => Str::upper(trim($data['PARENTESCO'] ?? '')),
                    ];
                }

                break; // Solo la primera hoja.
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /**
     * Decide qué hacer con cada fila, sin escribir nada.
     *
     * @param  list<array{row: int, first_name: string, last_name: string, document: string, document_type: string, relationship_code: string}>  $rows
     * @param  list<int>  $forcedRows  Filas con posible duplicado por nombre que se autorizó crear tras revisarlas.
     * @return list<array<string, mixed>>
     */
    public static function plan(AffiliationCorporate $affiliation, array $rows, array $forcedRows = []): array
    {
        $existing = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliation->id)
            ->get(['id', 'first_name', 'last_name', 'nro_identificacion', 'status']);

        $byDocument = [];
        $byName = [];

        foreach ($existing as $affiliate) {
            $digits = self::documentKey($affiliate->nro_identificacion);
            if ($digits !== '') {
                $byDocument[$digits][] = $affiliate;
            }

            $byName[] = [self::nameTokens($affiliate->first_name.' '.$affiliate->last_name), $affiliate];
        }

        $forced = array_flip($forcedRows);
        $seen = [];
        $decisions = [];

        foreach ($rows as $row) {
            $key = self::documentKey($row['document']);
            $fullName = trim($row['first_name'].' '.$row['last_name']);
            $decision = ['excel_row' => $row['row'], 'name' => $fullName, 'document' => $row['document'], 'document_type' => $row['document_type'], 'match' => null];

            if ($row['first_name'] === '' || $key === '') {
                $decisions[] = array_merge($decision, ['action' => self::ACTION_INVALID, 'detail' => 'Falta el nombre o el documento.']);

                continue;
            }

            $seenKey = $key.'|'.implode(' ', self::nameTokens($fullName));
            if (isset($seen[$seenKey])) {
                $decisions[] = array_merge($decision, ['action' => self::ACTION_REPEATED, 'detail' => 'Misma persona que la fila '.$seen[$seenKey].'.']);

                continue;
            }
            $seen[$seenKey] = $row['row'];

            if (isset($byDocument[$key])) {
                $match = $byDocument[$key][0];
                $decisions[] = array_merge($decision, ['action' => self::ACTION_EXISTS, 'detail' => 'Documento ya registrado ('.$match->status.').', 'match' => self::describe($match)]);

                continue;
            }

            $nameMatch = self::findByName($fullName, $byName);

            if ($nameMatch !== null && ! isset($forced[$row['row']])) {
                $decisions[] = array_merge($decision, ['action' => self::ACTION_NAME_MATCH, 'detail' => 'El nombre coincide con un afiliado de otro documento. Revíselo; si es otra persona, autorícela con --forzar-fila='.$row['row'].'.', 'match' => self::describe($nameMatch)]);

                continue;
            }

            $decisions[] = array_merge($decision, ['action' => self::ACTION_CREATE, 'detail' => $nameMatch !== null ? 'Autorizada a mano pese a coincidir por nombre.' : 'No está en la afiliación.', 'match' => $nameMatch !== null ? self::describe($nameMatch) : null, 'source' => $row]);
        }

        return $decisions;
    }

    /**
     * Fila de plan del colectivo. Debe ser única: con varias no hay forma segura de
     * elegir plan y tarifa para personas sin edad.
     */
    public static function planRow(AffiliationCorporate $affiliation): AfilliationCorporatePlan
    {
        $rows = AfilliationCorporatePlan::query()->where('affiliation_corporate_id', $affiliation->id)->get();

        if ($rows->count() !== 1) {
            throw new RuntimeException('La afiliación '.$affiliation->code.' tiene '.$rows->count().' filas de plan; el comando solo admite colectivos con un único plan y tarifa.');
        }

        $row = $rows->first();

        if (! is_numeric($row->fee) || (float) $row->fee <= 0 || ! $row->plan_id) {
            throw new RuntimeException('La fila de plan de '.$affiliation->code.' no tiene plan o tarifa válidos.');
        }

        return $row;
    }

    /**
     * @return array{poblation: int, fee_anual: float, total_amount: float, plan_total_persons: int}
     */
    public static function totals(AffiliationCorporate $affiliation, AfilliationCorporatePlan $planRow): array
    {
        $affiliation->refresh();
        $planRow->refresh();

        return [
            'poblation' => (int) $affiliation->poblation,
            'fee_anual' => round((float) $affiliation->fee_anual, 2),
            'total_amount' => round((float) $affiliation->total_amount, 2),
            'plan_total_persons' => (int) $planRow->total_persons,
        ];
    }

    /**
     * Crea los afiliados marcados «crear» y actualiza totales, en una sola transacción
     * con la afiliación bloqueada. Si los totales finales no son los esperados,
     * revierte todo.
     *
     * @param  list<array<string, mixed>>  $decisions
     * @return array{created: list<int>, before: array<string, int|float>, after: array<string, int|float>}
     */
    public static function execute(AffiliationCorporate $affiliation, array $decisions, ?User $user, string $reason): array
    {
        $toCreate = array_values(array_filter($decisions, static fn (array $decision): bool => $decision['action'] === self::ACTION_CREATE));

        if ($toCreate === []) {
            throw new RuntimeException('No hay personas para crear.');
        }

        return DB::transaction(function () use ($affiliation, $toCreate, $user, $reason): array {
            $locked = AffiliationCorporate::query()->lockForUpdate()->findOrFail($affiliation->id);
            $planRow = self::planRow($locked);
            $before = self::totals($locked, $planRow);

            // Otra corrida pudo crearlos entre la vista previa y ahora: se vuelve a decidir con el bloqueo puesto.
            $recheck = self::plan($locked, array_map(static fn (array $decision): array => $decision['source'], $toCreate), array_map(static fn (array $decision): int => (int) $decision['excel_row'], array_filter($toCreate, static fn (array $decision): bool => $decision['match'] !== null)));
            $toCreate = array_values(array_filter($recheck, static fn (array $decision): bool => $decision['action'] === self::ACTION_CREATE));

            if ($toCreate === []) {
                throw new RuntimeException('Mientras tanto ya se registraron todas: no hay nada que crear.');
            }

            $fee = round((float) $planRow->fee, 2);
            $created = [];

            foreach ($toCreate as $decision) {
                $source = $decision['source'];

                $affiliate = new AffiliateCorporate;
                $affiliate->forceFill([
                    'affiliation_corporate_id' => $locked->id,
                    'affiliation_type' => 'ESTANDARD',
                    'first_name' => mb_substr($source['first_name'], 0, 100),
                    'last_name' => mb_substr($source['last_name'], 0, 100),
                    'nro_identificacion' => self::storedDocument($source['document']),
                    'birth_date' => null,
                    'age' => null,
                    'sex' => null,
                    'relationship' => null,
                    'condition_medical' => 'SANO',
                    'plan_id' => $planRow->plan_id,
                    'coverage_id' => $planRow->coverage_id,
                    'fee' => $fee,
                    'subtotal_anual' => $fee,
                    'subtotal_payment_frequency' => round(CorporateAffiliateRemovalService::annualFeeToPerPeriodAmount($fee, $locked->payment_frequency), 2),
                    'subtotal_daily' => round($fee / 30, 2),
                    'payment_frequency' => $locked->payment_frequency,
                    'status' => 'ACTIVO',
                    'business_unit_id' => $locked->business_unit_id,
                    'business_line_id' => $locked->business_line_id,
                    'specific_business_unit' => $locked->specific_business_unit,
                    'created_by' => $user?->id,
                ]);
                $affiliate->save();

                $created[] = (int) $affiliate->id;
            }

            CorporateAffiliatePlanSyncService::syncOwnerTotalsFromAffiliates($locked, ['ACTIVO']);

            // Sin edad no entran al recálculo por rango: se suman a mano (decisión de Negocios).
            $planRow->total_persons = (int) $planRow->total_persons + count($created);
            CorporateAffiliateRemovalService::recalculateCorporatePlanRowTotals($planRow);
            $planRow->save();

            $after = self::totals($locked, $planRow);
            $count = count($created);

            $expected = [
                'poblation' => $before['poblation'] + $count,
                'fee_anual' => round($before['fee_anual'] + $fee * $count, 2),
                'plan_total_persons' => $before['plan_total_persons'] + $count,
            ];

            foreach ($expected as $field => $value) {
                if (abs((float) $after[$field] - (float) $value) > 0.01) {
                    throw new RuntimeException("Los totales no cuadran ({$field}: se esperaba {$value} y quedó {$after[$field]}). No se guardó nada.");
                }
            }

            SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_AFFILIATES_BULK_ADDED', 'console.affiliations-corporate.add-missing', [
                'affiliation_code' => $locked->code,
                'reason' => $reason,
                'created_ids' => $created,
                'totals_before' => $before,
                'totals_after' => $after,
                'fee_per_person' => $fee,
                'birth_date_policy' => 'sin fecha ni edad; total_persons ajustado a mano',
            ], $user);

            return ['created' => $created, 'before' => $before, 'after' => $after];
        });
    }

    /**
     * Solo dígitos y sin ceros a la izquierda: «V-010.444.385» y «10444385» son la misma.
     */
    public static function documentKey(?string $document): string
    {
        return ltrim(preg_replace('/\D+/', '', (string) $document) ?? '', '0');
    }

    /**
     * Cédula de adulto: solo dígitos (como el resto del colectivo). Cédula de menor
     * (lleva sufijo): tal cual, sin espacios, para no perder ceros ni el sufijo.
     */
    public static function storedDocument(string $document): string
    {
        $clean = preg_replace('/\s+/', '', trim($document)) ?? '';

        return str_contains($clean, '-') && preg_match('/^\d[\d\-]*$/', $clean) === 1
            ? $clean
            : (preg_replace('/\D+/', '', $clean) ?? '');
    }

    /**
     * @return list<string>
     */
    public static function nameTokens(?string $name): array
    {
        $ascii = Str::upper(Str::ascii((string) $name));
        $tokens = array_values(array_filter(preg_split('/[^A-Z]+/', $ascii) ?: [], static fn (string $token): bool => strlen($token) > 2));
        sort($tokens);

        return $tokens;
    }

    /**
     * Mismo criterio que la comparación hecha con el padrón de REPSOL: todas las
     * palabras iguales, o al menos tres en común (tolera una letra mal escrita o un
     * segundo apellido omitido). Las palabras con «?» (eñe dañada) no cuentan.
     *
     * @param  list<array{0: list<string>, 1: AffiliateCorporate}>  $byName
     */
    private static function findByName(string $fullName, array $byName): ?AffiliateCorporate
    {
        $tokens = array_values(array_filter(self::nameTokens(str_replace('?', '#', $fullName)), static fn (string $token): bool => ! str_contains($token, '#')));

        if (count($tokens) < 2) {
            return null;
        }

        $need = min(3, count($tokens));
        $best = null;
        $bestScore = 0;

        foreach ($byName as [$candidateTokens, $affiliate]) {
            $score = count(array_intersect($tokens, $candidateTokens));

            if ($score >= $need && $score > $bestScore) {
                $best = $affiliate;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private static function describe(AffiliateCorporate $affiliate): string
    {
        return trim($affiliate->first_name.' '.$affiliate->last_name).' · doc. '.$affiliate->nro_identificacion.' · '.$affiliate->status.' · id '.$affiliate->id;
    }

    private static function cleanName(string $value): string
    {
        return Str::upper(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    private static function cellToString(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (is_float($value) && floor($value) === $value) {
            return number_format($value, 0, '', '');
        }

        return trim((string) $value);
    }
}
