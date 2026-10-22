<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Support\Audit\TableRowSnapshot;

/**
 * Foto exacta de las columnas que toca la unificación de grupo familiar.
 *
 * La mecánica (lectura cruda, comparación y restitución) vive en {@see TableRowSnapshot};
 * aquí solo se declara qué columnas toca la unificación.
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
        return TableRowSnapshot::capture(self::COLUMNS, $idsByTable);
    }

    /**
     * @param  array<string, array<int|string, array<string, string|null>>>  $snapshot
     * @return array<string, list<int>>
     */
    public static function idsOf(array $snapshot): array
    {
        return TableRowSnapshot::idsOf(self::COLUMNS, $snapshot);
    }

    /**
     * @param  array<string, array<int|string, array<string, string|null>>>  $expected
     * @param  array<string, array<int|string, array<string, string|null>>>  $current
     * @return list<array{table: string, id: int, column: string|null, expected: string|null, current: string|null}>
     */
    public static function differences(array $expected, array $current): array
    {
        return TableRowSnapshot::differences(self::COLUMNS, $expected, $current);
    }

    /**
     * @param  array<string, array<int|string, array<string, string|null>>>  $snapshot
     */
    public static function restore(array $snapshot): int
    {
        return TableRowSnapshot::restore(self::COLUMNS, $snapshot);
    }

    public static function sameValue(?string $left, ?string $right): bool
    {
        return TableRowSnapshot::sameValue($left, $right);
    }
}
