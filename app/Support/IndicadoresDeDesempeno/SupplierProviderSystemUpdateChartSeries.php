<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use App\Models\Log;
use App\Models\SupplierContactPrincipal;
use App\Models\SupplierRedGlobal;
use App\Models\SupplierZonaCobertura;
use Illuminate\Database\Eloquent\Builder;

final class SupplierProviderSystemUpdateChartSeries
{
    public const LABEL_JURIDICOS = 'Proveedores jurídicos';

    public const LABEL_NATURALES = 'Proveedores naturales';

    private const AUDIT_SUPPLIER_UPDATED = 'AUDIT_OPERATIONS_SUPPLIER_UPDATED';

    private const AUDIT_SUPPLIER_DOCUMENT_UPLOADED = 'AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_UPLOADED';

    private const AUDIT_DOCTOR_NURSE_UPDATED = 'AUDIT_OPERATIONS_DOCTOR_NURSE_UPDATED';

    private const AUDIT_DOCTOR_NURSE_DOCUMENT_UPLOADED = 'AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_UPLOADED';

    /**
     * Campos del proveedor jurídico que corresponden a contactos, correos, baremos, documentos o infraestructura.
     *
     * @var list<string>
     */
    private const SUPPLIER_RELEVANT_FIELDS = [
        'local_phone',
        'personal_phone',
        'correo_principal',
        'supplier_clasificacion_id',
        'state_services',
        'type_service',
        'documents',
        'carta_acceptance',
        'afiliacion_proveedor',
        'urgen_care',
        'descripcion_urgen_care',
        'consulta_aps',
        'descripcion_consulta_aps',
        'amd',
        'descripcion_amd',
        'laboratorio_centro',
        'descripcion_laboratorio_centro',
        'laboratorio_domicilio',
        'descripcion_laboratorio_domicilio',
        'rx_centro',
        'descripcion_rx_centro',
        'rx_domicilio',
        'descripcion_rx_domicilio',
        'eco_abdominal_centro',
        'descripcion_eco_abdominal_centro',
        'eco_abdominal_domicilio',
        'descripcion_eco_abdominal_domicilio',
        'densitometria_osea',
        'descripcion_densitometria_osea',
        'dialisis',
        'descripcion_dialisis',
        'electrocardiograma_centro',
        'descripcion_electrocardiograma_centro',
        'electrocardiograma_domicilio',
        'descripcion_electrocardiograma_domicilio',
        'equipos_especiales_oftalmologia',
        'descripcion_equipos_especiales_oftalmologia',
        'mamografia',
        'descripcion_mamografia',
        'quirofanos',
        'descripcion_quirofanos',
        'radioterapia_intraoperatoria',
        'descripcion_radioterapia_intraoperatoria',
        'resonancia',
        'descripcion_resonancia',
        'tomografo',
        'descripcion_tomografo',
        'oncologia',
        'descripcion_encologogia',
        'uci_uten',
        'descripcion_uci_uten',
        'neonatal',
        'descripcion_neonatal',
        'ambulancias',
        'descripcion_ambulancias',
        'odontologia',
        'descripcion_odontologia',
        'oftalmologia',
        'descripcion_oftalmologia',
        'uci_pediatrica',
        'descripcion_uci_pediatrica',
        'uci_adulto',
        'descripcion_uci_adulto',
        'estacionamiento_propio',
        'descripcion_estacionamiento_propio',
        'ascensor',
        'descripcion_ascensor',
        'robotica',
        'descripcion_robotica',
        'otras_unidades_especiales',
        'descripcion_otras_unidades_especiales',
        'cirugia_general',
        'descripcion_cirugia_general',
        'medicina_interna',
        'descripcion_medicina_interna',
        'obstetricia_ginecologia',
        'descripcion_obstetricia_ginecologia',
        'pediatria',
        'descripcion_pediatria',
        'otorrinolaringologia',
        'descripcion_otorrinolaringologia',
        'traumatologia_ortopedia',
        'descripcion_traumatologia_ortopedia',
        'neumonologia',
        'descripcion_neumonologia',
        'gastroenterologia',
        'descripcion_gastroenterologia',
        'cardiocirugia',
        'descripcion_cardiocirugia',
        'cardiologia',
        'descripcion_cardiologia',
        'psiquiatria',
        'descripcion_psiquiatria',
        'anestesia_reanimacion',
        'descripcion_anestesia_reanimacion',
        'imagenologia_avanzada',
        'descripcion_imagenologia_avanzada',
        'unidad_uci',
        'descripcion_unidad_uci',
        'banco_sangre',
        'descripcion_banco_sangre',
        'nefrologia',
        'descripcion_nefrologia',
        'radioterapia',
        'descripcion_radioterapia',
        'quimioterapia',
        'descripcion_quimioterapia',
        'otros_servicios',
    ];

    /**
     * Campos del proveedor natural que corresponden a contactos, correos, baremos, documentos o infraestructura.
     *
     * @var list<string>
     */
    private const DOCTOR_NURSE_RELEVANT_FIELDS = [
        'personal_phone',
        'local_phone',
        'correo_principal',
        'coverage_zone',
        'supplier_clasificacion_id',
        'tipo_clinica',
        'documents',
        'carta_acceptance',
        'afiliacion_proveedor',
        'equip_diag_vital_signs',
        'equip_desc_diag_vital_signs',
        'equip_diag_oximeter',
        'equip_desc_diag_oximeter',
        'equip_diag_thermometer',
        'equip_desc_diag_thermometer',
        'equip_diag_exam_kit',
        'equip_desc_diag_exam_kit',
        'equip_diag_glucometer',
        'equip_desc_diag_glucometer',
        'equip_diag_flashlight_hammer',
        'equip_desc_diag_flashlight_hammer',
        'equip_care_gloves',
        'equip_desc_care_gloves',
        'equip_care_antiseptics',
        'equip_desc_care_antiseptics',
        'equip_care_supplies',
        'equip_desc_care_supplies',
        'equip_care_sharps_container',
        'equip_desc_care_sharps_container',
        'equip_support_hygiene',
        'equip_desc_support_hygiene',
        'equip_support_scissors_forceps',
        'equip_desc_support_scissors_forceps',
        'equip_support_prescriptions_stamps',
        'equip_desc_support_prescriptions_stamps',
        'equip_adv_basic_medicines',
        'equip_desc_adv_basic_medicines',
        'equip_adv_catheters_aspiration',
        'equip_desc_adv_catheters_aspiration',
        'equip_adv_emergency_bag',
        'equip_desc_adv_emergency_bag',
    ];

    /**
     * @return array{labels: list<string>, juridicos: list<int>, naturales: list<int>}
     */
    public static function groupedByCollaborator(?int $year = null, ?string $from = null, ?string $to = null): array
    {
        /** @var array<string, int> $juridicos */
        $juridicos = self::juridicosCountsByCollaborator($year, $from, $to);

        /** @var array<string, int> $naturales */
        $naturales = self::naturalesCountsByCollaborator($year, $from, $to);

        $collaborators = collect(array_keys($juridicos))
            ->merge(array_keys($naturales))
            ->unique()
            ->sort(function (string $left, string $right) use ($juridicos, $naturales): int {
                $leftTotal = ($juridicos[$left] ?? 0) + ($naturales[$left] ?? 0);
                $rightTotal = ($juridicos[$right] ?? 0) + ($naturales[$right] ?? 0);

                if ($leftTotal !== $rightTotal) {
                    return $rightTotal <=> $leftTotal;
                }

                return strcmp($left, $right);
            })
            ->values()
            ->all();

        if ($collaborators === []) {
            return [
                'labels' => [],
                'juridicos' => [],
                'naturales' => [],
            ];
        }

        $juridicosData = [];
        $naturalesData = [];

        foreach ($collaborators as $collaborator) {
            $juridicosData[] = (int) ($juridicos[$collaborator] ?? 0);
            $naturalesData[] = (int) ($naturales[$collaborator] ?? 0);
        }

        return [
            'labels' => $collaborators,
            'juridicos' => $juridicosData,
            'naturales' => $naturalesData,
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function juridicosCountsByCollaborator(?int $year, ?string $from = null, ?string $to = null): array
    {
        $counts = self::countsFromAuditLogs(
            $year,
            $from,
            $to,
            [self::AUDIT_SUPPLIER_UPDATED, self::AUDIT_SUPPLIER_DOCUMENT_UPLOADED],
            self::SUPPLIER_RELEVANT_FIELDS,
        );
        $counts = self::mergeCounts($counts, self::countsFromRelatedTable(SupplierContactPrincipal::query(), $year, $from, $to));
        $counts = self::mergeCounts($counts, self::countsFromRelatedTable(SupplierZonaCobertura::query(), $year, $from, $to));
        $counts = self::mergeCounts($counts, self::countsFromRelatedTable(SupplierRedGlobal::query(), $year, $from, $to));

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private static function naturalesCountsByCollaborator(?int $year, ?string $from = null, ?string $to = null): array
    {
        return self::countsFromAuditLogs(
            $year,
            $from,
            $to,
            [self::AUDIT_DOCTOR_NURSE_UPDATED, self::AUDIT_DOCTOR_NURSE_DOCUMENT_UPLOADED],
            self::DOCTOR_NURSE_RELEVANT_FIELDS,
        );
    }

    /**
     * @param  list<string>  $actions
     * @param  list<string>  $relevantFields
     * @return array<string, int>
     */
    private static function countsFromAuditLogs(?int $year, ?string $from, ?string $to, array $actions, array $relevantFields): array
    {
        $logs = Log::query()
            ->whereIn('action', $actions)
            ->tap(fn (Builder $query): Builder => IndicadoresDeDesempenoPeriodFilter::apply($query, 'created_at', $year, $from, $to))
            ->get(['action', 'response']);

        $counts = [];

        foreach ($logs as $log) {
            $payload = json_decode((string) $log->response, true);

            if (! is_array($payload)) {
                continue;
            }

            $details = $payload['details'] ?? null;

            if (! is_array($details)) {
                continue;
            }

            $collaborator = self::resolveCollaboratorName($details, $payload);

            if ($collaborator === null) {
                continue;
            }

            if ($log->action === self::AUDIT_SUPPLIER_DOCUMENT_UPLOADED
                || $log->action === self::AUDIT_DOCTOR_NURSE_DOCUMENT_UPLOADED) {
                $counts[$collaborator] = ($counts[$collaborator] ?? 0) + 1;

                continue;
            }

            $changedFields = $details['changed_fields'] ?? [];

            if (! is_array($changedFields) || $changedFields === []) {
                continue;
            }

            if (! self::hasRelevantFieldChange($changedFields, $relevantFields)) {
                continue;
            }

            $counts[$collaborator] = ($counts[$collaborator] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  Builder<SupplierContactPrincipal>|Builder<SupplierRedGlobal>|Builder<SupplierZonaCobertura>  $query
     * @return array<string, int>
     */
    private static function countsFromRelatedTable(Builder $query, ?int $year, ?string $from = null, ?string $to = null): array
    {
        $aggregates = $query
            ->whereColumn('updated_at', '>', 'created_at')
            ->tap(fn (Builder $builder): Builder => IndicadoresDeDesempenoPeriodFilter::apply($builder, 'updated_at', $year, $from, $to))
            ->tap(fn (Builder $builder): Builder => self::applyCollaboratorFilter($builder, 'updated_by'))
            ->selectRaw('TRIM(updated_by) as collaborator, COUNT(*) as total')
            ->groupByRaw('TRIM(updated_by)')
            ->orderByDesc('total')
            ->orderBy('collaborator')
            ->get();

        $counts = [];

        foreach ($aggregates as $row) {
            $counts[(string) $row->collaborator] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $base
     * @param  array<string, int>  $additional
     * @return array<string, int>
     */
    private static function mergeCounts(array $base, array $additional): array
    {
        foreach ($additional as $collaborator => $total) {
            $base[$collaborator] = ($base[$collaborator] ?? 0) + $total;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $payload
     */
    private static function resolveCollaboratorName(array $details, array $payload): ?string
    {
        $candidates = [
            $details['updated_by'] ?? null,
            $payload['user']['name'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $normalized = trim($candidate);

            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param  list<string>|array<int, string>  $changedFields
     * @param  list<string>  $relevantFields
     */
    private static function hasRelevantFieldChange(array $changedFields, array $relevantFields): bool
    {
        foreach ($changedFields as $field) {
            if (! is_string($field)) {
                continue;
            }

            if (in_array($field, $relevantFields, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<SupplierContactPrincipal>|Builder<SupplierRedGlobal>|Builder<SupplierZonaCobertura>  $query
     * @return Builder<SupplierContactPrincipal>|Builder<SupplierRedGlobal>|Builder<SupplierZonaCobertura>
     */
    private static function applyCollaboratorFilter(Builder $query, string $column): Builder
    {
        return $query
            ->whereNotNull($column)
            ->whereRaw("NULLIF(TRIM({$column}), '') IS NOT NULL");
    }

    /**
     * @return list<string>
     */
    public static function supplierRelevantFieldNames(): array
    {
        return self::SUPPLIER_RELEVANT_FIELDS;
    }

    /**
     * @return list<string>
     */
    public static function doctorNurseRelevantFieldNames(): array
    {
        return self::DOCTOR_NURSE_RELEVANT_FIELDS;
    }
}
