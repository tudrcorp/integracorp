<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;
use App\Support\CommercialStructureBankingExportColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Ficha completa de cada nodo de la jerarquía para el reporte en Excel, más el diagnóstico
 * de qué le falta a cada registro.
 *
 * Agencias y agentes comparten casi todas las columnas (identidad, contacto, ubicación,
 * datos bancarios, comisiones y expediente), así que conviven en la misma hoja: donde un
 * dato solo existe en uno de los dos (representante legal en la agencia, fecha de
 * nacimiento en el agente) la celda del otro queda vacía en vez de desalinear la fila.
 */
final class CommercialHierarchyDetailColumns
{
    /**
     * Campos sin los cuales un registro no se puede operar ni cobrar. El resto de columnas
     * se exportan igual, pero no penalizan la completitud.
     *
     * @var array<string, list<string>>
     */
    private const REQUIRED_GROUPS = [
        'Identificación' => ['rif', 'identification', 'name'],
        'Contacto' => ['email', 'phone'],
        'Ubicación' => ['address', 'country', 'state', 'city'],
        'Datos bancarios' => [
            'local_beneficiary_name',
            'local_beneficiary_rif',
            'local_beneficiary_account_number',
            'local_beneficiary_account_bank',
            'local_beneficiary_account_type',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'rif' => 'RIF',
        'identification' => 'Cédula',
        'name' => 'Nombre',
        'email' => 'Email',
        'phone' => 'Teléfono',
        'address' => 'Dirección',
        'country' => 'País',
        'state' => 'Estado',
        'city' => 'Ciudad',
        'local_beneficiary_name' => 'Beneficiario',
        'local_beneficiary_rif' => 'RIF beneficiario',
        'local_beneficiary_account_number' => 'Nº de cuenta',
        'local_beneficiary_account_bank' => 'Banco',
        'local_beneficiary_account_type' => 'Tipo de cuenta',
    ];

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            // Diagnóstico primero: es la columna por la que el analista va a filtrar.
            'Información completa',
            'Completitud %',
            'Campos faltantes',
            'Grupos incompletos',

            'RIF',
            'Cédula',
            'Representante legal',
            'Fecha de nacimiento',
            'Sexo',
            'Estado civil',

            'Email',
            'Teléfono',
            'Instagram',
            'Nombre contacto 2',
            'Email contacto 2',
            'Teléfono contacto 2',

            'Dirección',
            'Complemento de dirección',
            'País',
            'Región',
            'Estado',
            'Ciudad',

            ...CommercialStructureBankingExportColumns::csvHeaders(),

            'Maneja TDEC',
            'Maneja TDEV',
            'Comisión TDEC',
            'Comisión TDEC renovación',
            'Comisión TDEV',
            'Comisión TDEV renovación',
            'Crédito asignado',
            'Es referidor',
            '% referidor',

            'Doc. CI/RIF',
            'Doc. W8/W9',
            'Doc. cuenta USD',
            'Doc. cuenta BsD',
            'Doc. cuenta Zelle',
            'Doc. acuerdo',
            'Doc. planilla',
            'Firma digital',
            'Condiciones aceptadas',

            'Creado por',
            'Fecha de registro',
            'Fecha de creación',
            'Última actualización',
        ];
    }

    /**
     * Carga en una sola consulta por tipo las fichas de todos los nodos exportados: recorrer
     * la jerarquía registro a registro dispararía una consulta por agente.
     *
     * Las agencias se buscan además por código como respaldo, porque el diagrama construye
     * algunos nodos con un `select` parcial y un id ausente dejaría esa fila sin ficha.
     *
     * @param  list<array{entity_type: string, entity_id: int|null, code?: string}>  $rows
     * @return array{agency: array<int, Agency>, agent: array<int, Agent>, agency_by_code: array<string, Agency>}
     */
    public static function loadEntities(array $rows): array
    {
        $ids = ['agency' => [], 'agent' => []];
        $pendingCodes = [];

        foreach ($rows as $row) {
            $id = $row['entity_id'] ?? null;
            $type = $row['entity_type'] ?? '';

            if ($id !== null && isset($ids[$type])) {
                $ids[$type][] = (int) $id;

                continue;
            }

            $code = strtoupper(trim((string) ($row['code'] ?? '')));

            if ($type === 'agency' && $code !== '' && $code !== 'SIN CÓDIGO') {
                $pendingCodes[] = $code;
            }
        }

        $relations = ['country:id,name', 'state:id,definition', 'city:id,definition', 'region:id,definition'];

        $agencies = $ids['agency'] === []
            ? []
            : Agency::query()->with($relations)->whereIn('id', array_unique($ids['agency']))->get()->keyBy('id')->all();

        $agenciesByCode = [];

        if ($pendingCodes !== []) {
            $agenciesByCode = Agency::query()
                ->with($relations)
                ->whereIn(DB::raw('UPPER(TRIM(code))'), array_unique($pendingCodes))
                ->get()
                ->keyBy(fn (Agency $agency): string => strtoupper(trim((string) $agency->code)))
                ->all();
        }

        return [
            'agency' => $agencies,
            'agent' => $ids['agent'] === []
                ? []
                : Agent::query()->with($relations)->whereIn('id', array_unique($ids['agent']))->get()->keyBy('id')->all(),
            'agency_by_code' => $agenciesByCode,
        ];
    }

    /**
     * @param  array{entity_type: string, entity_id: int|null, code: string}  $row
     * @param  array{agency: array<int, Agency>, agent: array<int, Agent>, agency_by_code: array<string, Agency>}  $entities
     */
    public static function resolve(array $row, array $entities): ?Model
    {
        $type = $row['entity_type'];

        if ($row['entity_id'] !== null) {
            $record = $entities[$type][$row['entity_id']] ?? null;

            if ($record instanceof Model) {
                return $record;
            }
        }

        if ($type !== 'agency') {
            return null;
        }

        return $entities['agency_by_code'][strtoupper(trim($row['code']))] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function values(?Model $record): array
    {
        if (! $record instanceof Model) {
            return self::emptyRow();
        }

        $isAgency = $record instanceof Agency;
        $audit = self::completeness($record);

        return [
            $audit['complete'] ? 'Sí' : 'No',
            $audit['percentage'].'%',
            $audit['missing'] === [] ? '—' : implode(', ', $audit['missing']),
            $audit['missing_groups'] === [] ? '—' : implode(', ', $audit['missing_groups']),

            self::text($record, 'rif'),
            self::text($record, $isAgency ? 'ci_responsable' : 'ci'),
            $isAgency ? self::text($record, 'name_representative') : '',
            $isAgency ? '' : self::date($record, 'birth_date'),
            $isAgency ? '' : self::text($record, 'sex'),
            $isAgency ? '' : self::text($record, 'marital_status'),

            self::text($record, 'email'),
            self::text($record, 'phone'),
            self::text($record, 'user_instagram'),
            self::text($record, 'name_contact_2'),
            self::text($record, 'email_contact_2'),
            self::text($record, 'phone_contact_2'),

            self::text($record, 'address'),
            self::text($record, 'address_complement'),
            self::country($record),
            self::region($record),
            self::state($record),
            self::city($record),

            ...CommercialStructureBankingExportColumns::valuesFromModel($record),

            self::flag($record, 'tdec'),
            self::flag($record, 'tdev'),
            self::text($record, 'commission_tdec'),
            self::text($record, 'commission_tdec_renewal'),
            self::text($record, 'commission_tdev'),
            self::text($record, 'commission_tdev_renewal'),
            self::text($record, 'assigned_credit'),
            self::flag($record, 'is_referidor'),
            self::text($record, 'referidor_percentage'),

            self::document($record, 'file_ci_rif'),
            self::document($record, 'file_w8_w9'),
            self::document($record, 'file_account_usd'),
            self::document($record, 'file_account_bsd'),
            self::document($record, 'file_account_zelle'),
            $isAgency ? self::document($record, 'file_acuerdo') : '',
            $isAgency ? self::document($record, 'file_planilla') : '',
            self::document($record, 'fir_dig_agent'),
            self::flag($record, 'is_accepted_conditions'),

            self::text($record, 'created_by'),
            self::date($record, 'date_register'),
            self::date($record, 'created_at'),
            self::date($record, 'updated_at'),
        ];
    }

    /**
     * @return array{complete: bool, percentage: int, missing: list<string>, missing_groups: list<string>}
     */
    public static function completeness(Model $record): array
    {
        $isAgency = $record instanceof Agency;
        $missing = [];
        $missingGroups = [];
        $required = 0;
        $filled = 0;

        foreach (self::REQUIRED_GROUPS as $group => $fields) {
            $groupHasGap = false;

            foreach ($fields as $field) {
                $required++;

                if (self::requiredFieldIsFilled($record, $field, $isAgency)) {
                    $filled++;

                    continue;
                }

                $groupHasGap = true;
                $missing[] = self::FIELD_LABELS[$field] ?? $field;
            }

            if ($groupHasGap) {
                $missingGroups[] = $group;
            }
        }

        return [
            'complete' => $missing === [],
            'percentage' => $required === 0 ? 100 : (int) round(($filled / $required) * 100),
            'missing' => $missing,
            'missing_groups' => $missingGroups,
        ];
    }

    private static function requiredFieldIsFilled(Model $record, string $field, bool $isAgency): bool
    {
        $value = match ($field) {
            'identification' => $record->getAttribute($isAgency ? 'ci_responsable' : 'ci'),
            'name' => $record->getAttribute($isAgency ? 'name_corporative' : 'name'),
            'country' => self::country($record),
            'state' => self::state($record),
            'city' => self::city($record),
            default => $record->getAttribute($field),
        };

        return trim((string) ($value ?? '')) !== '';
    }

    /**
     * @return list<string>
     */
    private static function emptyRow(): array
    {
        $values = array_fill(0, count(self::headers()), '');
        $values[0] = 'No aplica';
        $values[1] = '—';
        $values[2] = '—';
        $values[3] = '—';

        return $values;
    }

    private static function country(Model $record): string
    {
        return self::relationLabel($record, 'country', 'name');
    }

    private static function region(Model $record): string
    {
        $label = self::relationLabel($record, 'region', 'definition');

        /** En registros antiguos `region` es un texto en la propia tabla, no una relación. */
        return $label !== '' ? $label : trim((string) ($record->getAttribute('region') ?? ''));
    }

    private static function state(Model $record): string
    {
        return self::relationLabel($record, 'state', 'definition');
    }

    private static function city(Model $record): string
    {
        return self::relationLabel($record, 'city', 'definition');
    }

    private static function relationLabel(Model $record, string $relation, string $attribute): string
    {
        $related = $record->relationLoaded($relation) ? $record->getRelation($relation) : $record->{$relation};

        return $related instanceof Model ? trim((string) ($related->getAttribute($attribute) ?? '')) : '';
    }

    private static function text(Model $record, string $field): string
    {
        return trim((string) ($record->getAttribute($field) ?? ''));
    }

    private static function flag(Model $record, string $field): string
    {
        $value = $record->getAttribute($field);

        if ($value === null || $value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
    }

    /**
     * Un adjunto solo importa como presencia: la ruta interna no le dice nada al analista.
     */
    private static function document(Model $record, string $field): string
    {
        return self::text($record, $field) !== '' ? 'Cargado' : 'Faltante';
    }

    private static function date(Model $record, string $field): string
    {
        $value = $record->getAttribute($field);

        if (blank($value)) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return trim((string) $value);
        }
    }
}
