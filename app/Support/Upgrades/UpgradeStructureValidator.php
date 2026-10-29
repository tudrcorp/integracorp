<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

/**
 * Reglas de forma de un upgrade del catálogo, sin base de datos: las usa el
 * formulario para avisar antes de guardar y la persistencia para no escribir
 * nunca una estructura inválida.
 *
 * La regla central: dentro de una cobertura, los rangos de edad no se solapan.
 * Si dos rangos cubrieran la misma edad, el precio de esa persona sería ambiguo.
 */
final class UpgradeStructureValidator
{
    public const MIN_AGE = 0;

    public const MAX_AGE = 120;

    public const MAX_NAME_LENGTH = 120;

    public const MAX_SERVICE_NAME_LENGTH = 160;

    public const MAX_PRICE = 99999.99;

    public const MAX_COVERAGE_AMOUNT = 9999999999.99;

    public const MAX_SERVICES = 50;

    public const MAX_COVERAGES = 20;

    public const MAX_RANGES_PER_COVERAGE = 30;

    /**
     * Deja los datos del formulario en una forma estable: listas sin claves de
     * repetidor, textos recortados, números como número o null.
     *
     * @param  array<string, mixed>  $data
     * @return array{name: string, description: ?string, status: string, services: list<array{id: ?int, name: string, description: ?string}>, coverages: list<array{id: ?int, amount: ?float, ranges: list<array{id: ?int, age_from: ?int, age_to: ?int, price: ?float}>}>}
     */
    public static function normalize(array $data): array
    {
        $services = [];

        foreach (array_values((array) ($data['services'] ?? [])) as $service) {
            $service = (array) $service;
            $services[] = [
                'id' => self::intOrNull($service['id'] ?? null),
                'name' => self::cleanText($service['name'] ?? ''),
                'description' => self::nullableText($service['description'] ?? null),
            ];
        }

        $coverages = [];

        foreach (array_values((array) ($data['coverages'] ?? [])) as $coverage) {
            $coverage = (array) $coverage;
            $ranges = [];

            foreach (array_values((array) ($coverage['ranges'] ?? [])) as $range) {
                $range = (array) $range;
                $ranges[] = [
                    'id' => self::intOrNull($range['id'] ?? null),
                    'age_from' => self::intOrNull($range['age_from'] ?? null, allowZero: true),
                    'age_to' => self::intOrNull($range['age_to'] ?? null, allowZero: true),
                    'price' => self::floatOrNull($range['price'] ?? null),
                ];
            }

            $coverages[] = [
                'id' => self::intOrNull($coverage['id'] ?? null),
                'amount' => self::floatOrNull($coverage['amount'] ?? null),
                'ranges' => $ranges,
            ];
        }

        $status = mb_strtoupper(trim((string) ($data['status'] ?? 'ACTIVO')), 'UTF-8');

        return [
            'name' => mb_strtoupper(self::cleanText($data['name'] ?? ''), 'UTF-8'),
            'description' => self::nullableText($data['description'] ?? null),
            'image_path' => self::imagePath($data['image_path'] ?? null),
            'status' => $status !== '' ? $status : 'ACTIVO',
            'services' => $services,
            'coverages' => $coverages,
        ];
    }

    /**
     * Ruta de la imagen de la tarjeta: solo dentro de la carpeta de imágenes de upgrades (el
     * FileUpload a veces entrega un arreglo con un solo elemento).
     */
    private static function imagePath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = array_values($value)[0] ?? null;
        }

        $path = is_string($value) ? trim($value) : '';

        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, \App\Models\Upgrade::IMAGE_DIRECTORY.'/')) {
            return null;
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $data  datos ya normalizados o crudos del formulario
     * @return list<string> mensajes en español, vacío si todo está bien
     */
    public static function errors(array $data): array
    {
        $data = self::normalize($data);
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Indique el nombre del upgrade.';
        } elseif (mb_strlen($data['name']) > self::MAX_NAME_LENGTH) {
            $errors[] = 'El nombre del upgrade supera los '.self::MAX_NAME_LENGTH.' caracteres.';
        }

        if (! in_array($data['status'], ['ACTIVO', 'INACTIVO'], true)) {
            $errors[] = 'El estatus del upgrade debe ser Activo o Inactivo.';
        }

        $errors = [...$errors, ...self::serviceErrors($data['services'])];

        if ($data['coverages'] === []) {
            $errors[] = 'Agregue al menos una cobertura con sus rangos de edad y precios.';
        }

        if (count($data['coverages']) > self::MAX_COVERAGES) {
            $errors[] = 'Un upgrade admite hasta '.self::MAX_COVERAGES.' coberturas.';
        }

        $seenAmounts = [];

        foreach ($data['coverages'] as $index => $coverage) {
            $label = self::coverageLabel($coverage['amount'], $index);

            if ($coverage['amount'] !== null && ($coverage['amount'] <= 0 || $coverage['amount'] > self::MAX_COVERAGE_AMOUNT)) {
                $errors[] = $label.': el monto de la cobertura debe ser mayor a 0.';
            }

            $amountKey = $coverage['amount'] === null ? 'sin-monto' : number_format($coverage['amount'], 2, '.', '');

            if (isset($seenAmounts[$amountKey])) {
                $errors[] = $coverage['amount'] === null
                    ? 'Solo puede haber una cobertura sin monto. Indique el monto de las demás.'
                    : $label.' está repetida. Cada cobertura debe tener un monto distinto.';
            }

            $seenAmounts[$amountKey] = true;
            $errors = [...$errors, ...self::rangeErrors($coverage['ranges'], $label)];
        }

        return array_values(array_unique($errors));
    }

    /**
     * Mensaje del primer solape entre rangos de una misma cobertura, o null.
     *
     * @param  list<array{age_from: ?int, age_to: ?int}>  $ranges
     */
    public static function overlapMessage(array $ranges): ?string
    {
        $valid = array_values(array_filter(
            $ranges,
            fn (array $range): bool => $range['age_from'] !== null && $range['age_to'] !== null && $range['age_from'] <= $range['age_to'],
        ));

        usort($valid, fn (array $a, array $b): int => [$a['age_from'], $a['age_to']] <=> [$b['age_from'], $b['age_to']]);

        for ($i = 1, $count = count($valid); $i < $count; $i++) {
            $previous = $valid[$i - 1];
            $current = $valid[$i];

            if ($current['age_from'] <= $previous['age_to']) {
                return 'el rango '.self::rangeLabel($previous).' se solapa con '.self::rangeLabel($current)
                    .'. Una edad solo puede estar en un rango.';
            }
        }

        return null;
    }

    public static function coverageLabel(?float $amount, int $index): string
    {
        return $amount !== null
            ? 'Cobertura '.number_format($amount, 2, ',', '.').' US$'
            : 'Cobertura '.($index + 1);
    }

    /**
     * @param  list<array{id: ?int, name: string, description: ?string}>  $services
     * @return list<string>
     */
    private static function serviceErrors(array $services): array
    {
        $errors = [];
        $seen = [];

        if (count($services) > self::MAX_SERVICES) {
            $errors[] = 'Un upgrade admite hasta '.self::MAX_SERVICES.' servicios.';
        }

        foreach ($services as $index => $service) {
            if ($service['name'] === '') {
                $errors[] = 'El servicio '.($index + 1).' no tiene nombre.';

                continue;
            }

            if (mb_strlen($service['name']) > self::MAX_SERVICE_NAME_LENGTH) {
                $errors[] = 'El servicio «'.$service['name'].'» supera los '.self::MAX_SERVICE_NAME_LENGTH.' caracteres.';
            }

            $key = mb_strtoupper($service['name'], 'UTF-8');

            if (isset($seen[$key])) {
                $errors[] = 'El servicio «'.$service['name'].'» está repetido.';
            }

            $seen[$key] = true;
        }

        return $errors;
    }

    /**
     * @param  list<array{id: ?int, age_from: ?int, age_to: ?int, price: ?float}>  $ranges
     * @return list<string>
     */
    private static function rangeErrors(array $ranges, string $label): array
    {
        if ($ranges === []) {
            return [$label.': agregue al menos un rango de edad con su precio.'];
        }

        $errors = [];

        if (count($ranges) > self::MAX_RANGES_PER_COVERAGE) {
            $errors[] = $label.': admite hasta '.self::MAX_RANGES_PER_COVERAGE.' rangos de edad.';
        }

        foreach ($ranges as $index => $range) {
            $position = $label.', rango '.($index + 1);

            if ($range['age_from'] === null || $range['age_to'] === null) {
                $errors[] = $position.': indique la edad desde y hasta.';
            } elseif ($range['age_from'] < self::MIN_AGE || $range['age_to'] > self::MAX_AGE) {
                $errors[] = $position.': las edades deben estar entre '.self::MIN_AGE.' y '.self::MAX_AGE.' años.';
            } elseif ($range['age_from'] > $range['age_to']) {
                $errors[] = $position.': la edad desde ('.$range['age_from'].') no puede ser mayor que la edad hasta ('.$range['age_to'].').';
            }

            if ($range['price'] === null || $range['price'] <= 0) {
                $errors[] = $position.': el precio debe ser mayor a 0.';
            } elseif ($range['price'] > self::MAX_PRICE) {
                $errors[] = $position.': el precio no puede superar '.number_format(self::MAX_PRICE, 2, ',', '.').' US$.';
            }
        }

        $overlap = self::overlapMessage($ranges);

        if ($overlap !== null) {
            $errors[] = $label.': '.$overlap;
        }

        return $errors;
    }

    /**
     * @param  array{age_from: ?int, age_to: ?int}  $range
     */
    private static function rangeLabel(array $range): string
    {
        return $range['age_from'].'–'.$range['age_to'];
    }

    private static function cleanText(mixed $value): string
    {
        return preg_replace('/\s+/u', ' ', trim(is_scalar($value) ? (string) $value : '')) ?? '';
    }

    private static function nullableText(mixed $value): ?string
    {
        $text = trim(is_scalar($value) ? (string) $value : '');

        return $text !== '' ? $text : null;
    }

    private static function intOrNull(mixed $value, bool $allowZero = false): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value) || (float) $value !== floor((float) $value)) {
            return null;
        }

        $int = (int) $value;

        if ($allowZero) {
            return $int;
        }

        return $int > 0 ? $int : null;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
