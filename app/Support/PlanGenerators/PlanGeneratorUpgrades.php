<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;
use App\Models\PlanGeneratorUpgrade;
use App\Models\Upgrade;
use App\Models\UpgradeCoverage;
use App\Models\UpgradeCoverageAgeRange;
use App\Models\UpgradeService;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Upgrades opcionales de una cotización del generador de planes.
 *
 * La cotización no conoce edades, solo cuántas personas hay en cada rango que
 * escribió el analista («0 a 30 años»). El precio del upgrade se resuelve por
 * rango de la cotización: se toma el rango del upgrade que contiene esas
 * edades y, si el rango de la cotización atraviesa dos rangos del upgrade, el
 * precio más alto.
 *
 * El upgrade se aplica por rango completo. Por defecto entran todos los rangos
 * con precio; el analista puede sacar los que quiera (`excluded_ranges`, se
 * guardan los excluidos para que un rango nuevo entre solo). Un rango sin precio
 * que el upgrade no cubre en ninguna edad «no aplica». Un rango que el upgrade
 * cubre solo en parte (cotización «0 a 30», upgrade desde 18) no se cobra salvo
 * que el analista lo incluya a mano (`manual_ranges`): declara cuántas personas
 * del rango tienen las edades cubiertas y escribe por qué; el precio es el del
 * upgrade, fijo. Quién y cuándo lo fija el servidor al guardar. Solo se bloquea
 * un upgrade que no se aplica a nadie.
 *
 * Al aprobar, cada persona recibe el upgrade por su edad real
 * (`eligibilityForAge()`): nunca alguien fuera de las edades del upgrade.
 *
 * Cada línea guarda una copia congelada (nombre, cobertura, precios por edad y
 * servicios). Al editar, una línea que ya existía conserva su copia aunque el
 * catálogo haya cambiado; una nueva toma el catálogo vigente. La copia nunca
 * viene del navegador: se arma siempre en el servidor.
 *
 * El costo anual del upgrade se suma al total grupal de cada columna (la tarifa
 * individual por rango no cambia). Al aprobar, cada afiliado recibe el upgrade
 * con el precio de su rango cotizado.
 */
final class PlanGeneratorUpgrades
{
    public const MAX_LINES = 10;

    public const STATUS_INCLUDED = 'included';

    public const STATUS_EXCLUDED = 'excluded';

    public const STATUS_NO_PRICE = 'no_price';

    /** Rango que el upgrade cubre solo en parte: no se cobra hasta incluirlo a mano. */
    public const STATUS_PARTIAL = 'partial';

    /** Rango cubierto en parte que el analista incluyó a mano, con cuántas personas y por qué. */
    public const STATUS_MANUAL = 'manual';

    public const MANUAL_NOTE_MIN_LENGTH = 15;

    public const MANUAL_NOTE_MAX_LENGTH = 500;

    /**
     * Estado del repetidor del formulario.
     *
     * @return array<string, array{line_key: string, upgrade_coverage_id: int|null, excluded_ranges: list<string>, manual_ranges: list<array<string, mixed>>}>
     */
    public static function formState(PlanGenerator $plan): array
    {
        if (! $plan->exists) {
            return [];
        }

        $state = [];

        foreach ($plan->upgrades()->get() as $line) {
            $state[(string) Str::uuid()] = [
                'line_key' => (string) $line->line_key,
                'upgrade_coverage_id' => $line->upgrade_coverage_id,
                'excluded_ranges' => self::normalizeExcluded($line->excluded_ranges),
                'manual_ranges' => self::normalizeManual($line->manual_ranges),
            ];
        }

        return $state;
    }

    /**
     * Líneas guardadas en la cotización.
     *
     * @return list<array<string, mixed>>
     */
    public static function linesFromModel(?PlanGenerator $plan): array
    {
        if ($plan === null) {
            return [];
        }

        if (! $plan->relationLoaded('upgrades') && ! $plan->exists) {
            return [];
        }

        $upgrades = $plan->relationLoaded('upgrades') ? $plan->upgrades : $plan->upgrades()->get();

        return $upgrades->map(fn (PlanGeneratorUpgrade $line): array => self::lineFromModel($line))->values()->all();
    }

    /**
     * Arma las líneas del estado del formulario en el servidor: congeladas si ya
     * estaban en la cotización con la misma cobertura; del catálogo si son nuevas.
     *
     * @param  array<int|string, mixed>  $state
     * @return array{lines: list<array<string, mixed>>, errors: list<string>}
     */
    public static function resolveLines(?PlanGenerator $plan, array $state): array
    {
        $stored = collect(self::linesFromModel($plan))->keyBy('line_key');
        $lines = [];
        $errors = [];
        $seenUpgrades = [];

        $items = array_values(array_filter($state, 'is_array'));

        if (count($items) > self::MAX_LINES) {
            $errors[] = 'Una cotización admite hasta '.self::MAX_LINES.' upgrades.';
        }

        foreach ($items as $index => $item) {
            $coverageId = is_numeric($item['upgrade_coverage_id'] ?? null) ? (int) $item['upgrade_coverage_id'] : 0;

            if ($coverageId <= 0) {
                $errors[] = 'El upgrade '.($index + 1).' no tiene cobertura elegida. Elíjala o quite la fila.';

                continue;
            }

            $lineKey = is_string($item['line_key'] ?? null) && $item['line_key'] !== '' ? $item['line_key'] : (string) Str::uuid();
            $frozen = $stored->get($lineKey);

            if (is_array($frozen) && (int) $frozen['upgrade_coverage_id'] === $coverageId) {
                $line = $frozen;
            } else {
                $line = self::lineFromCatalog($coverageId, $lineKey);

                if (is_string($line)) {
                    $errors[] = $line;

                    continue;
                }
            }

            // La población elegida es decisión del analista: siempre sale del formulario.
            $line['excluded_ranges'] = self::normalizeExcluded($item['excluded_ranges'] ?? []);

            $manual = self::resolveManual(
                (array) ($item['manual_ranges'] ?? []),
                is_array($frozen) ? self::normalizeManual($frozen['manual_ranges'] ?? []) : [],
                (string) $line['upgrade_name'],
            );
            array_push($errors, ...$manual['errors']);
            $line['manual_ranges'] = $manual['entries'];

            $upgradeKey = (string) ($line['upgrade_id'] ?? $line['upgrade_name']);

            if (isset($seenUpgrades[$upgradeKey])) {
                $errors[] = 'El upgrade «'.$line['upgrade_name'].'» está dos veces. Elija una sola cobertura por upgrade.';

                continue;
            }

            $seenUpgrades[$upgradeKey] = true;
            $lines[] = $line;
        }

        return ['lines' => $lines, 'errors' => array_values(array_unique($errors))];
    }

    /**
     * Costo de cada upgrade frente a los rangos y la población de la cotización.
     *
     * Cada fila trae su `status`: `included` (el upgrade cubre todo el rango y
     * se cobra a toda su población), `excluded` (el analista lo sacó), `partial`
     * (lo cubre solo en parte; no se cobra), `manual` (cubierto en parte e
     * incluido a mano: se cobra a las personas declaradas) o `no_price` (no cubre
     * ninguna edad del rango, o el rango no se puede leer).
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<int|string, mixed>  $rateRows
     * @return array{
     *     upgrades: list<array<string, mixed>>,
     *     annual_total: float,
     *     population: int,
     *     errors: list<string>
     * }
     */
    public static function quote(array $lines, array $rateRows): array
    {
        $rateRows = array_values(array_filter($rateRows, 'is_array'));
        $population = 0;

        foreach ($rateRows as $rateRow) {
            $population += max(0, (int) ($rateRow['population'] ?? 0));
        }

        $upgrades = [];
        $errors = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $excluded = self::normalizeExcluded($line['excluded_ranges'] ?? []);
            $agePrices = (array) $line['age_prices'];
            $rows = [];
            $annual = 0.0;
            $persons = 0;

            foreach ($rateRows as $index => $rateRow) {
                $label = trim((string) ($rateRow['age_range_label'] ?? ''));
                $rowPopulation = max(0, (int) ($rateRow['population'] ?? 0));
                $full = self::priceForLabel($agePrices, $label);
                $partial = $full === null ? self::partialCoverage($agePrices, $label) : null;
                $manualEntry = $partial !== null ? self::manualEntryFor($line, $label) : null;
                $price = $full ?? $partial;

                $status = match (true) {
                    $full !== null && in_array(self::rangeKey($label), $excluded, true) => self::STATUS_EXCLUDED,
                    $full !== null => self::STATUS_INCLUDED,
                    $manualEntry !== null => self::STATUS_MANUAL,
                    $partial !== null => self::STATUS_PARTIAL,
                    default => self::STATUS_NO_PRICE,
                };

                $chargedPersons = match ($status) {
                    self::STATUS_INCLUDED => $rowPopulation,
                    self::STATUS_MANUAL => min((int) $manualEntry['persons'], $rowPopulation),
                    default => 0,
                };

                if ($status === self::STATUS_MANUAL && (int) $manualEntry['persons'] > $rowPopulation) {
                    $errors[] = '«'.$line['upgrade_name'].'», rango «'.$label.'»: declaró '.$manualEntry['persons'].' personas con la edad del upgrade, pero el rango tiene '.$rowPopulation.'. Corrija la cantidad.';
                }

                $subtotal = $chargedPersons > 0 ? round((float) $price['price'] * $chargedPersons, 2) : 0.0;
                $annual += $subtotal;
                $persons += $chargedPersons;

                $rows[] = [
                    'index' => $index,
                    'label' => $label,
                    'key' => self::rangeKey($label),
                    'population' => $rowPopulation,
                    'charged_persons' => $chargedPersons,
                    'price' => $price['price'] ?? null,
                    'range_id' => $price['range_id'] ?? null,
                    'covered' => $partial['covered'] ?? null,
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'manual' => $manualEntry,
                ];
            }

            if ($persons === 0 && $population > 0) {
                $errors[] = 'El upgrade «'.$line['upgrade_name'].'» no se aplica a ninguna persona: incluya al menos un rango con población o quite el upgrade.';
            }

            $chargedPrices = array_values(array_unique(array_map(
                static fn (array $row): string => number_format((float) $row['price'], 2, '.', ''),
                self::chargedRows(['rows' => $rows]),
            )));

            $upgrades[] = $line + [
                'rows' => $rows,
                'annual' => round($annual, 2),
                'persons' => $persons,
                'population' => $population,
                'covers' => self::coverageSpansLabel($agePrices),
                'uniform_price' => count($chargedPrices) === 1 ? (float) $chargedPrices[0] : null,
            ];
            $total += $annual;
        }

        return [
            'upgrades' => $upgrades,
            'annual_total' => round($total, 2),
            'population' => $population,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * Parte del rango escrito que el upgrade sí cubre, cuando no lo cubre entero:
     * las edades cubiertas («18 a 30 años») y el precio por persona (el más alto
     * entre esas edades). Null si cubre todo el rango, ninguna edad, o el rango no
     * se puede leer.
     *
     * @param  list<array<string, mixed>>  $agePrices
     * @return array{price: float, range_id: int|null, covered: string, from: int, to: int}|null
     */
    public static function partialCoverage(array $agePrices, string $label): ?array
    {
        [$min, $max] = PlanGeneratorCatalogPublisher::ageRangeBounds($label);

        if ($min === null || $max === null || $min > $max || $max > 120 || self::priceForBounds($agePrices, $min, $max) !== null) {
            return null;
        }

        $covered = [];
        $best = null;

        for ($age = $min; $age <= $max; $age++) {
            $match = self::rangeForAge($agePrices, $age);

            if ($match === null) {
                continue;
            }

            $covered[] = $age;

            if ($best === null || (float) $match['price'] > (float) $best['price']) {
                $best = $match;
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'price' => round((float) $best['price'], 2),
            'range_id' => isset($best['id']) && is_numeric($best['id']) ? (int) $best['id'] : null,
            'covered' => self::spansLabel($covered),
            'from' => $covered[0],
            'to' => $covered[count($covered) - 1],
        ];
    }

    /**
     * Edades que cubre el upgrade, para explicarlo: «18 a 64 años».
     *
     * @param  list<array<string, mixed>>  $agePrices
     */
    public static function coverageSpansLabel(array $agePrices): string
    {
        $ages = [];

        foreach ($agePrices as $range) {
            for ($age = max(0, (int) ($range['age_from'] ?? 0)); $age <= min(120, (int) ($range['age_to'] ?? -1)); $age++) {
                $ages[$age] = true;
            }
        }

        ksort($ages);

        return self::spansLabel(array_keys($ages));
    }

    /**
     * Si una persona de esa edad recibe cada upgrade, con su precio por edad real
     * o el motivo por el que no. Es la única regla de asignación al aprobar:
     *
     * - sin edad, no recibe;
     * - su edad tiene que estar en un rango de precio del upgrade;
     * - su rango de la cotización no puede estar excluido;
     * - si ese rango solo estaba cubierto en parte, tiene que haberse incluido a mano.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{line_key: string, name: string, receives: bool, reason: string, upgrade: array<string, mixed>|null}>
     */
    public static function eligibilityForAge(array $lines, ?int $age, ?string $rowLabel): array
    {
        $result = [];

        foreach ($lines as $line) {
            $name = (string) $line['upgrade_name'];
            $agePrices = (array) $line['age_prices'];
            $label = trim((string) $rowLabel);
            $deny = static fn (string $reason): array => ['line_key' => (string) $line['line_key'], 'name' => $name, 'receives' => false, 'reason' => $reason, 'upgrade' => null];

            if ($age === null) {
                $result[] = $deny('no tiene una edad válida en el padrón');

                continue;
            }

            $range = self::rangeForAge($agePrices, $age);

            if ($range === null) {
                $result[] = $deny('tiene '.$age.' años y el upgrade cubre '.self::coverageSpansLabel($agePrices));

                continue;
            }

            if ($label === '') {
                $result[] = $deny('su edad no cae en ningún rango de la cotización');

                continue;
            }

            if (self::priceForLabel($agePrices, $label) !== null) {
                if (in_array(self::rangeKey($label), self::normalizeExcluded($line['excluded_ranges'] ?? []), true)) {
                    $result[] = $deny('su rango «'.$label.'» se sacó del upgrade en la cotización');

                    continue;
                }
            } elseif (self::manualEntryFor($line, $label) === null) {
                $result[] = $deny('su rango «'.$label.'» no se incluyó en el upgrade en la cotización');

                continue;
            }

            $result[] = [
                'line_key' => (string) $line['line_key'],
                'name' => $name,
                'receives' => true,
                'reason' => 'tiene '.$age.' años',
                'upgrade' => [
                    'upgrade_id' => $line['upgrade_id'],
                    'upgrade_coverage_id' => $line['upgrade_coverage_id'],
                    'upgrade_coverage_age_range_id' => isset($range['id']) && is_numeric($range['id']) ? (int) $range['id'] : null,
                    'name' => $name,
                    'coverage_amount' => $line['coverage_amount'],
                    'amount' => round((float) $range['price'], 2),
                ],
            ];
        }

        return $result;
    }

    /**
     * Upgrades que recibe una persona de esa edad, y su suma.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{total: float, upgrades: list<array<string, mixed>>}
     */
    public static function perPersonForAge(array $lines, ?int $age, ?string $rowLabel): array
    {
        $upgrades = [];

        foreach (self::eligibilityForAge($lines, $age, $rowLabel) as $item) {
            if ($item['receives']) {
                $upgrades[] = $item['upgrade'];
            }
        }

        return ['total' => round(array_sum(array_column($upgrades, 'amount')), 2), 'upgrades' => $upgrades];
    }

    /**
     * Rango escrito de la cotización que contiene la edad.
     *
     * @param  array<int|string, mixed>  $rateRows
     */
    public static function rowLabelForAge(array $rateRows, ?int $age): ?string
    {
        if ($age === null) {
            return null;
        }

        foreach ($rateRows as $rateRow) {
            $label = trim((string) (is_array($rateRow) ? ($rateRow['age_range_label'] ?? '') : ($rateRow->age_range_label ?? '')));
            [$min, $max] = PlanGeneratorCatalogPublisher::ageRangeBounds($label);

            if ($min !== null && $max !== null && $min <= $age && $age <= $max) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Filas que se cobran, para imprimirlas (con población).
     *
     * @param  array<string, mixed>  $upgrade  elemento de `quote()['upgrades']`
     * @return list<array<string, mixed>>
     */
    public static function chargedRows(array $upgrade): array
    {
        return array_values(array_filter(
            (array) ($upgrade['rows'] ?? []),
            static fn (array $row): bool => in_array($row['status'], [self::STATUS_INCLUDED, self::STATUS_MANUAL], true) && (int) ($row['charged_persons'] ?? $row['population']) > 0,
        ));
    }

    /**
     * Clave con la que se compara un rango escrito: sin mayúsculas ni espacios de más.
     */
    public static function rangeKey(string $label): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($label)) ?? '', 'UTF-8');
    }

    /**
     * @return list<string>
     */
    public static function normalizeExcluded(mixed $excluded): array
    {
        if (is_string($excluded)) {
            $decoded = json_decode($excluded, true);
            $excluded = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($excluded)) {
            return [];
        }

        $keys = [];

        foreach ($excluded as $label) {
            if (is_scalar($label) && trim((string) $label) !== '') {
                $keys[self::rangeKey((string) $label)] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Precio por persona del upgrade para un rango escrito de la cotización.
     * Null si el rango no se puede leer o alguna de sus edades no tiene precio.
     *
     * @param  list<array<string, mixed>>  $agePrices
     * @return array{price: float, range_id: int|null}|null
     */
    public static function priceForLabel(array $agePrices, string $label): ?array
    {
        [$min, $max] = PlanGeneratorCatalogPublisher::ageRangeBounds($label);

        /** Igual que la pre-afiliación: el rango necesita edad mínima y máxima. */
        if ($min === null || $max === null || $min > $max || $max > 120) {
            return null;
        }

        return self::priceForBounds($agePrices, $min, $max);
    }

    /**
     * @param  list<array<string, mixed>>  $agePrices
     * @return array{price: float, range_id: int|null}|null
     */
    public static function priceForBounds(array $agePrices, int $min, int $max): ?array
    {
        $best = null;

        for ($age = $min; $age <= $max; $age++) {
            $match = null;

            foreach ($agePrices as $range) {
                if ((int) ($range['age_from'] ?? -1) <= $age && $age <= (int) ($range['age_to'] ?? -1)) {
                    $match = $range;

                    break;
                }
            }

            if ($match === null) {
                return null;
            }

            if ($best === null || (float) $match['price'] > (float) $best['price']) {
                $best = $match;
            }
        }

        return $best === null ? null : [
            'price' => round((float) $best['price'], 2),
            'range_id' => isset($best['id']) && is_numeric($best['id']) ? (int) $best['id'] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $agePrices
     * @return array<string, mixed>|null
     */
    private static function rangeForAge(array $agePrices, int $age): ?array
    {
        foreach ($agePrices as $range) {
            if ((int) ($range['age_from'] ?? -1) <= $age && $age <= (int) ($range['age_to'] ?? -1)) {
                return $range;
            }
        }

        return null;
    }

    /**
     * «18 a 30 años» o «18 a 30 y 40 a 45 años» a partir de edades ordenadas.
     *
     * @param  list<int>  $ages
     */
    private static function spansLabel(array $ages): string
    {
        if ($ages === []) {
            return 'ninguna edad';
        }

        $spans = [];
        $start = $prev = $ages[0];

        foreach (array_slice($ages, 1) as $age) {
            if ($age !== $prev + 1) {
                $spans[] = $start === $prev ? (string) $start : $start.' a '.$prev;
                $start = $age;
            }

            $prev = $age;
        }

        $spans[] = $start === $prev ? (string) $start : $start.' a '.$prev;

        return implode(' y ', $spans).' años';
    }

    /**
     * Reemplaza los upgrades guardados por los del formulario. Debe correr dentro
     * de la transacción del guardado.
     *
     * @param  array<int|string, mixed>  $state
     *
     * @throws InvalidArgumentException si alguna línea no se puede armar
     */
    public static function sync(PlanGenerator $plan, array $state): void
    {
        $resolved = self::resolveLines($plan, $state);

        if ($resolved['errors'] !== []) {
            throw new InvalidArgumentException(implode(' ', $resolved['errors']));
        }

        $changes = self::manualChanges(self::linesFromModel($plan), $resolved['lines']);

        self::write($plan, $resolved['lines']);

        if ($changes['added'] !== [] || $changes['removed'] !== []) {
            SecurityAudit::log('AUDIT_BUSINESS_PLAN_GENERATOR_UPGRADE_MANUAL_RANGES', 'business.plan-generators.upgrades.manual-ranges', [
                'panel' => 'business',
                'module' => 'plan_generators',
                'plan_generator_id' => $plan->getKey(),
                'control_number' => $plan->control_number ?? null,
                'added' => $changes['added'],
                'removed' => $changes['removed'],
            ]);
        }
    }

    /**
     * Inclusión manual del rango en la línea, si la hay.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>|null
     */
    public static function manualEntryFor(array $line, string $label): ?array
    {
        $key = self::rangeKey($label);

        foreach (self::normalizeManual($line['manual_ranges'] ?? []) as $entry) {
            if ($entry['key'] === $key) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Inclusiones manuales bien formadas, sin repetir rango. Lo que no tenga
     * rango, cantidad de personas o nota se descarta (la validación de
     * `resolveLines` es la que avisa al analista).
     *
     * @return list<array{key: string, label: string, persons: int, note: string, by: ?string, at: ?string}>
     */
    public static function normalizeManual(mixed $manual): array
    {
        if (is_string($manual)) {
            $decoded = json_decode($manual, true);
            $manual = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($manual)) {
            return [];
        }

        $entries = [];

        foreach ($manual as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $label = trim((string) ($entry['label'] ?? ''));
            $key = self::rangeKey($label);
            $persons = self::personsValue($entry['persons'] ?? null);
            $note = self::cleanNote($entry['note'] ?? '');

            if ($key === '' || $persons < 1 || $note === '') {
                continue;
            }

            $entries[$key] = [
                'key' => $key,
                'label' => $label,
                'persons' => $persons,
                'note' => $note,
                'by' => is_string($entry['by'] ?? null) && $entry['by'] !== '' ? $entry['by'] : null,
                'at' => is_string($entry['at'] ?? null) && $entry['at'] !== '' ? $entry['at'] : null,
            ];
        }

        return array_values($entries);
    }

    /**
     * Valida y fija la traza de las inclusiones manuales del formulario. Quién y
     * cuándo no se toman del navegador: se conservan los guardados si nada cambió
     * y, si no, se ponen el usuario y la hora actuales.
     *
     * @param  array<int|string, mixed>  $submitted
     * @param  list<array<string, mixed>>  $stored
     * @return array{entries: list<array<string, mixed>>, errors: list<string>}
     */
    private static function resolveManual(array $submitted, array $stored, string $upgradeName): array
    {
        $storedByKey = collect($stored)->keyBy('key');
        $actor = trim((string) (Auth::user()?->name ?? '')) ?: 'system';
        $entries = [];
        $errors = [];

        foreach ($submitted as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $label = trim((string) ($entry['label'] ?? ''));
            $key = self::rangeKey($label);
            $note = self::cleanNote($entry['note'] ?? '');
            $persons = self::personsValue($entry['persons'] ?? null);
            $where = '«'.$upgradeName.'», rango «'.$label.'»';

            if ($key === '') {
                continue;
            }

            if ($persons < 1) {
                $errors[] = $where.': indique cuántas personas del rango tienen la edad del upgrade (al menos 1).';

                continue;
            }

            if (mb_strlen($note) < self::MANUAL_NOTE_MIN_LENGTH) {
                $errors[] = $where.': la nota de por qué se incluye a mano necesita al menos '.self::MANUAL_NOTE_MIN_LENGTH.' caracteres.';

                continue;
            }

            $previous = $storedByKey->get($key);
            $unchanged = is_array($previous)
                && (int) $previous['persons'] === $persons
                && $previous['note'] === mb_substr($note, 0, self::MANUAL_NOTE_MAX_LENGTH);

            $entries[$key] = [
                'key' => $key,
                'label' => $label,
                'persons' => $persons,
                'note' => mb_substr($note, 0, self::MANUAL_NOTE_MAX_LENGTH),
                'by' => $unchanged ? $previous['by'] : $actor,
                'at' => $unchanged ? $previous['at'] : now()->toIso8601String(),
            ];
        }

        return ['entries' => array_values($entries), 'errors' => $errors];
    }

    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @return array{added: list<array<string, mixed>>, removed: list<array<string, mixed>>}
     */
    private static function manualChanges(array $before, array $after): array
    {
        $index = static function (array $lines): array {
            $map = [];

            foreach ($lines as $line) {
                foreach (self::normalizeManual($line['manual_ranges'] ?? []) as $entry) {
                    $map[$line['upgrade_name'].'|'.$entry['key']] = [
                        'upgrade' => $line['upgrade_name'],
                        'range' => $entry['label'],
                        'persons' => $entry['persons'],
                        'note' => $entry['note'],
                    ];
                }
            }

            return $map;
        };

        $old = $index($before);
        $new = $index($after);

        return [
            'added' => array_values(array_filter($new, static fn (array $entry, string $key): bool => ! isset($old[$key]) || $old[$key] != $entry, ARRAY_FILTER_USE_BOTH)),
            'removed' => array_values(array_diff_key($old, $new)),
        ];
    }

    /**
     * Cantidad de personas como entero positivo; 0 si no es un entero válido.
     */
    private static function personsValue(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_string($value) && preg_match('/^\s*\d{1,6}\s*$/', $value) === 1) {
            return (int) trim($value);
        }

        if (is_float($value) && floor($value) === $value) {
            return max(0, (int) $value);
        }

        return 0;
    }

    private static function cleanNote(mixed $note): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags(is_scalar($note) ? (string) $note : '')));
    }

    /**
     * Copia los upgrades congelados de una cotización a otra (plantilla → derivada).
     */
    public static function copy(PlanGenerator $from, PlanGenerator $to): void
    {
        self::write($to, array_map(
            static fn (array $line): array => ['line_key' => (string) Str::uuid()] + $line,
            self::linesFromModel($from),
        ));
    }

    /**
     * Mensajes que impiden guardar la cotización con sus upgrades.
     *
     * @param  array<int|string, mixed>  $state
     * @param  array<int|string, mixed>  $rateRows
     * @return list<string>
     */
    public static function validationErrors(?PlanGenerator $plan, array $state, array $rateRows): array
    {
        $resolved = self::resolveLines($plan, $state);

        if ($resolved['lines'] === []) {
            return $resolved['errors'];
        }

        return array_values(array_unique([...$resolved['errors'], ...self::quote($resolved['lines'], $rateRows)['errors']]));
    }

    /**
     * Estimado por persona para un rango de la cotización (opciones de la
     * pre-afiliación individual): el precio del rango si el upgrade lo cubre y no
     * se sacó, o el de la parte cubierta si se incluyó a mano. Lo que se asigna de
     * verdad se decide por la edad real (`perPersonForAge()`).
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{total: float, upgrades: list<array<string, mixed>>}
     */
    public static function perPersonForLabel(array $lines, string $label): array
    {
        $upgrades = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $agePrices = (array) $line['age_prices'];
            $price = self::priceForLabel($agePrices, $label);

            if ($price !== null && in_array(self::rangeKey($label), self::normalizeExcluded($line['excluded_ranges'] ?? []), true)) {
                continue;
            }

            if ($price === null) {
                $price = self::manualEntryFor($line, $label) !== null ? self::partialCoverage($agePrices, $label) : null;
            }

            if ($price === null) {
                continue;
            }

            $upgrades[] = [
                'upgrade_id' => $line['upgrade_id'],
                'upgrade_coverage_id' => $line['upgrade_coverage_id'],
                'upgrade_coverage_age_range_id' => $price['range_id'],
                'name' => (string) $line['upgrade_name'],
                'coverage_amount' => $line['coverage_amount'],
                'amount' => $price['price'],
            ];
            $total += $price['price'];
        }

        return ['total' => round($total, 2), 'upgrades' => $upgrades];
    }

    /**
     * Etiqueta de la cobertura del upgrade, como en el catálogo.
     *
     * @param  array<string, mixed>  $line
     */
    public static function coverageLabel(array $line): string
    {
        return $line['coverage_amount'] !== null
            ? 'Cobertura '.self::money((float) $line['coverage_amount'])
            : 'Cobertura única';
    }

    /**
     * «$30» o «$35,50»: sin decimales cuando el monto es entero, como el total grupal.
     */
    public static function money(?float $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;

        return '$'.number_format($amount, $decimals, ',', '.');
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private static function write(PlanGenerator $plan, array $lines): void
    {
        $plan->upgrades()->delete();
        $actor = trim((string) (Auth::user()?->name ?? '')) ?: 'system';

        foreach (array_values($lines) as $sort => $line) {
            $plan->upgrades()->create([
                'line_key' => (string) $line['line_key'],
                'upgrade_id' => $line['upgrade_id'],
                'upgrade_coverage_id' => $line['upgrade_coverage_id'],
                'upgrade_code' => $line['upgrade_code'],
                'upgrade_name' => $line['upgrade_name'],
                'coverage_amount' => $line['coverage_amount'],
                'age_prices' => array_values((array) $line['age_prices']),
                'services' => array_values((array) $line['services']),
                'excluded_ranges' => self::normalizeExcluded($line['excluded_ranges'] ?? []),
                'manual_ranges' => self::normalizeManual($line['manual_ranges'] ?? []),
                'sort_order' => $sort,
                'created_by' => $line['created_by'] ?? $actor,
            ]);
        }

        $plan->unsetRelation('upgrades');
        /** El PDF se cachea por la huella del registro: cambiar upgrades debe invalidarla. */
        $plan->touch();
    }

    /**
     * @return array<string, mixed>|string línea, o el mensaje de por qué no se puede usar
     */
    private static function lineFromCatalog(int $coverageId, string $lineKey): array|string
    {
        $coverage = UpgradeCoverage::query()
            ->with(['upgrade.activeServices', 'activeAgeRanges'])
            ->find($coverageId);

        if ($coverage === null || $coverage->upgrade === null) {
            return 'Una de las coberturas de upgrade elegidas ya no existe. Quítela y elija otra.';
        }

        if ($coverage->status !== Upgrade::STATUS_ACTIVE || ! $coverage->upgrade->isActive()) {
            return '«'.$coverage->upgrade->name.' · '.$coverage->label().'» ya no está activo en el catálogo. Quítelo o elija otro.';
        }

        if ($coverage->activeAgeRanges->isEmpty()) {
            return '«'.$coverage->upgrade->name.' · '.$coverage->label().'» no tiene precios por edad.';
        }

        return [
            'line_key' => $lineKey,
            'upgrade_id' => (int) $coverage->upgrade_id,
            'upgrade_coverage_id' => (int) $coverage->getKey(),
            'upgrade_code' => (string) $coverage->upgrade->code,
            'upgrade_name' => (string) $coverage->upgrade->name,
            'coverage_amount' => $coverage->amount !== null ? round((float) $coverage->amount, 2) : null,
            'age_prices' => $coverage->activeAgeRanges
                ->map(fn (UpgradeCoverageAgeRange $range): array => [
                    'id' => (int) $range->getKey(),
                    'age_from' => (int) $range->age_from,
                    'age_to' => (int) $range->age_to,
                    'price' => round((float) $range->price, 2),
                ])
                ->values()
                ->all(),
            'services' => $coverage->upgrade->activeServices
                ->map(fn (UpgradeService $service): array => [
                    'name' => (string) $service->name,
                    'description' => $service->description,
                ])
                ->values()
                ->all(),
            'frozen' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lineFromModel(PlanGeneratorUpgrade $line): array
    {
        return [
            'line_key' => (string) $line->line_key,
            'upgrade_id' => $line->upgrade_id,
            'upgrade_coverage_id' => $line->upgrade_coverage_id,
            'upgrade_code' => $line->upgrade_code,
            'upgrade_name' => (string) $line->upgrade_name,
            'coverage_amount' => $line->coverage_amount !== null ? round((float) $line->coverage_amount, 2) : null,
            'age_prices' => array_values((array) $line->age_prices),
            'services' => array_values((array) $line->services),
            'excluded_ranges' => self::normalizeExcluded($line->excluded_ranges),
            'manual_ranges' => self::normalizeManual($line->manual_ranges),
            'created_by' => $line->created_by,
            'frozen' => true,
        ];
    }
}
