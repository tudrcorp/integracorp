<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use Illuminate\Support\Facades\DB;

/**
 * Foto exacta de las columnas que toca la unificación de grupo familiar.
 *
 * Se lee con el query builder (valores crudos de la base, sin casts) para que la
 * foto de «después» y la lectura al revertir sean comparables byte a byte, y se
 * restituye igual, sin pasar por mutadores ni observers.
 */
final class FamilyMergeSnapshot
{
    /**
     * Columnas por tabla que la unificación puede modificar. Nada fuera de esta
     * lista se toca al revertir.
     *
     * @var array<string, list<string>>
     */
    public const COLUMNS = [
        'affiliations' => [
            'status', 'fee_anual', 'total_amount', 'family_members', 'activated_at', 'feedback',
            'full_name_ti', 'nro_identificacion_ti', 'sex_ti', 'age', 'birth_date_ti', 'adress_ti',
            'city_id_ti', 'state_id_ti', 'country_id_ti', 'region_ti', 'phone_ti', 'email_ti', 'document',
            'effective_date', 'payment_frequency',
        ],
        'affiliates' => [
            'affiliation_id', 'relationship', 'plan_id', 'coverage_id', 'age_range_id',
            'fee', 'total_amount', 'payment_frequency', 'status',
        ],
        'collections' => [
            'status', 'affiliate_status', 'persons', 'total_amount', 'affiliate_full_name', 'affiliate_contact',
            'affiliate_ci_rif', 'affiliate_phone', 'affiliate_email', 'payment_frequency', 'plan_id', 'coverage_id',
        ],
        'annual_collections' => [
            'status', 'affiliate_status', 'persons', 'affiliate_full_name', 'affiliate_contact',
            'affiliate_ci_rif', 'affiliate_phone', 'affiliate_email', 'plan_id', 'coverage_id',
            'month_1', 'month_2', 'month_3', 'month_4', 'month_5', 'month_6',
            'month_7', 'month_8', 'month_9', 'month_10', 'month_11', 'month_12',
        ],
        'renovations' => [
            'status', 'updated_by',
        ],
        'telemedicine_patients' => [
            'afilliation_id', 'code_affiliation', 'plan_id', 'coverage_id', 'status_affiliation', 'type_affiliation',
        ],
    ];

    /**
     * Nombre de cada tabla para los mensajes al analista.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'affiliations' => 'Afiliación',
        'affiliates' => 'Afiliado',
        'collections' => 'Cuota',
        'annual_collections' => 'Cuota anual',
        'renovations' => 'Renovación',
        'telemedicine_patients' => 'Paciente de telemedicina',
    ];

    /**
     * @param  array<string, list<int>>  $idsByTable
     * @return array<string, array<int, array<string, string|null>>>
     */
    public static function capture(array $idsByTable): array
    {
        $snapshot = [];

        foreach (self::COLUMNS as $table => $columns) {
            $ids = array_values(array_unique(array_map(intval(...), $idsByTable[$table] ?? [])));
            $snapshot[$table] = [];

            if ($ids === []) {
                continue;
            }

            sort($ids);

            foreach (DB::table($table)->whereIn('id', $ids)->orderBy('id')->get(['id', ...$columns]) as $row) {
                $values = [];

                foreach ($columns as $column) {
                    $values[$column] = self::normalize($row->{$column} ?? null);
                }

                $snapshot[$table][(int) $row->id] = $values;
            }
        }

        return $snapshot;
    }

    /**
     * Ids presentes en una foto, por tabla.
     *
     * @param  array<string, array<int|string, array<string, string|null>>>  $snapshot
     * @return array<string, list<int>>
     */
    public static function idsOf(array $snapshot): array
    {
        $ids = [];

        foreach (array_keys(self::COLUMNS) as $table) {
            $ids[$table] = array_map(intval(...), array_keys($snapshot[$table] ?? []));
        }

        return $ids;
    }

    /**
     * Diferencias entre la foto de «después» y lo que hay hoy en la base.
     *
     * @param  array<string, array<int|string, array<string, string|null>>>  $expected
     * @param  array<string, array<int|string, array<string, string|null>>>  $current
     * @return list<array{table: string, id: int, column: string|null, expected: string|null, current: string|null}>
     */
    public static function differences(array $expected, array $current): array
    {
        $differences = [];

        foreach (array_keys(self::COLUMNS) as $table) {
            foreach ($expected[$table] ?? [] as $id => $columns) {
                $id = (int) $id;
                $now = $current[$table][$id] ?? null;

                if ($now === null) {
                    $differences[] = ['table' => $table, 'id' => $id, 'column' => null, 'expected' => null, 'current' => null];

                    continue;
                }

                foreach ($columns as $column => $value) {
                    if (! self::sameValue($value, $now[$column] ?? null)) {
                        $differences[] = [
                            'table' => $table,
                            'id' => $id,
                            'column' => $column,
                            'expected' => $value,
                            'current' => $now[$column] ?? null,
                        ];
                    }
                }
            }
        }

        return $differences;
    }

    /**
     * Restituye la foto: cada fila vuelve a sus valores previos en las columnas registradas.
     *
     * @param  array<string, array<int|string, array<string, string|null>>>  $snapshot
     * @return int Filas restituidas.
     */
    public static function restore(array $snapshot): int
    {
        $restored = 0;

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($snapshot[$table] ?? [] as $id => $values) {
                $payload = array_intersect_key($values, array_flip($columns));

                if ($payload === []) {
                    continue;
                }

                DB::table($table)->where('id', (int) $id)->update($payload);
                $restored++;
            }
        }

        return $restored;
    }

    public static function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * Compara como texto y, si ambos son numéricos, como número (120 = 120.00).
     */
    public static function sameValue(?string $left, ?string $right): bool
    {
        if ($left === $right) {
            return true;
        }

        if ($left === null || $right === null) {
            return false;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return abs((float) $left - (float) $right) < 0.000001;
        }

        return false;
    }
}
