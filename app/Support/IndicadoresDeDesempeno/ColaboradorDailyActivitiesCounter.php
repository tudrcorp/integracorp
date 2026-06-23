<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use App\Models\DoctorNurse;
use App\Models\DoctorNurseObservacion;
use App\Models\HelpDesk;
use App\Models\Log;
use App\Models\Supplier;
use App\Models\SupplierContactPrincipal;
use App\Models\SupplierObservacion;
use App\Models\SupplierRedGlobal;
use App\Models\SupplierZonaCobertura;
use Illuminate\Database\Eloquent\Builder;

final class ColaboradorDailyActivitiesCounter
{
    public const GAUGE_MAX = 30;

    public const THRESHOLD_LOW_BELOW = 10;

    public const THRESHOLD_MEDIUM_MIN = 10;

    public const THRESHOLD_MEDIUM_MAX = 20;

    public const THRESHOLD_HIGH_ABOVE = 20;

    private const AUDIT_SUPPLIER_UPDATED = 'AUDIT_OPERATIONS_SUPPLIER_UPDATED';

    private const AUDIT_SUPPLIER_DOCUMENT_UPLOADED = 'AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_UPLOADED';

    private const AUDIT_DOCTOR_NURSE_UPDATED = 'AUDIT_OPERATIONS_DOCTOR_NURSE_UPDATED';

    private const AUDIT_DOCTOR_NURSE_DOCUMENT_UPLOADED = 'AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_UPLOADED';

    private const CARTA_ACCEPTANCE_ROUTE_FRAGMENT = 'carta-acceptance.upload';

    private const DOCUMENT_TYPE_CARTA = 'CARTA_ACEPTACION';

    /**
     * @return array<string, string>
     */
    public static function collaboratorOptions(?int $year = null): array
    {
        $labels = SupplierObservationsChartSeries::collaboratorLabels($year);
        $options = [];

        foreach ($labels as $label) {
            $options[$label] = $label;
        }

        return $options;
    }

    /**
     * @return array{
     *     total: int,
     *     tickets: int,
     *     observaciones: int,
     *     actualizaciones: int,
     *     nuevos_proveedores: int,
     *     cartas_aceptacion: int
     * }
     */
    public static function breakdownForCollaboratorOnDate(string $collaborator, string $date): array
    {
        $tickets = self::countHelpdeskTickets($collaborator, $date);
        $observaciones = self::countObservations($collaborator, $date);
        $actualizaciones = self::countProviderUpdates($collaborator, $date);
        $nuevosProveedores = self::countNewProviders($collaborator, $date);
        $cartasAceptacion = self::countAcceptanceLetters($collaborator, $date);

        return [
            'total' => $tickets + $observaciones + $actualizaciones + $nuevosProveedores + $cartasAceptacion,
            'tickets' => $tickets,
            'observaciones' => $observaciones,
            'actualizaciones' => $actualizaciones,
            'nuevos_proveedores' => $nuevosProveedores,
            'cartas_aceptacion' => $cartasAceptacion,
        ];
    }

    /**
     * @return array{level: string, label: string, color: string, description: string}
     */
    public static function performanceMeta(int $dailyCount): array
    {
        if ($dailyCount < self::THRESHOLD_LOW_BELOW) {
            return [
                'level' => 'bajo',
                'label' => 'Bajo desempeño',
                'color' => '#ff3b30',
                'description' => 'Por debajo de las 10 actividades terminadas diarias.',
            ];
        }

        if ($dailyCount <= self::THRESHOLD_MEDIUM_MAX) {
            return [
                'level' => 'medio',
                'label' => 'Medio desempeño',
                'color' => '#ffcc00',
                'description' => 'Entre 10 y 20 actividades terminadas diarias.',
            ];
        }

        return [
            'level' => 'alto',
            'label' => 'Alto desempeño',
            'color' => '#34c759',
            'description' => 'Mayor de 20 actividades terminadas diarias.',
        ];
    }

    public static function needleRotationDegrees(int $dailyCount): float
    {
        $clamped = max(0, min(self::GAUGE_MAX, $dailyCount));

        return -90 + (($clamped / self::GAUGE_MAX) * 180);
    }

    private static function countHelpdeskTickets(string $collaborator, string $date): int
    {
        return (int) HelpDesk::query()
            ->where('created_by', $collaborator)
            ->whereDate('created_at', $date)
            ->count();
    }

    private static function countObservations(string $collaborator, string $date): int
    {
        $juridicos = (int) SupplierObservacion::query()
            ->whereRaw('TRIM(created_by) = ?', [$collaborator])
            ->whereDate('created_at', $date)
            ->count();

        $naturales = (int) DoctorNurseObservacion::query()
            ->whereRaw('TRIM(created_by) = ?', [$collaborator])
            ->whereDate('created_at', $date)
            ->count();

        return $juridicos + $naturales;
    }

    private static function countNewProviders(string $collaborator, string $date): int
    {
        $juridicos = (int) Supplier::query()
            ->whereRaw('TRIM(created_by) = ?', [$collaborator])
            ->whereDate('created_at', $date)
            ->whereNotNull('correo_principal')
            ->whereRaw("NULLIF(TRIM(correo_principal), '') IS NOT NULL")
            ->count();

        $naturales = (int) DoctorNurse::query()
            ->whereRaw('TRIM(created_by) = ?', [$collaborator])
            ->whereDate('created_at', $date)
            ->whereNotNull('correo_principal')
            ->whereRaw("NULLIF(TRIM(correo_principal), '') IS NOT NULL")
            ->count();

        return $juridicos + $naturales;
    }

    private static function countAcceptanceLetters(string $collaborator, string $date): int
    {
        return self::countAuditCartaUploads($collaborator, $date, self::AUDIT_SUPPLIER_DOCUMENT_UPLOADED)
            + self::countAuditCartaUploads($collaborator, $date, self::AUDIT_DOCTOR_NURSE_DOCUMENT_UPLOADED);
    }

    private static function countProviderUpdates(string $collaborator, string $date): int
    {
        $juridicos = self::countAuditUpdatesForCollaboratorOnDate(
            $collaborator,
            $date,
            [self::AUDIT_SUPPLIER_UPDATED, self::AUDIT_SUPPLIER_DOCUMENT_UPLOADED],
            SupplierProviderSystemUpdateChartSeries::supplierRelevantFieldNames(),
        );

        $naturales = self::countAuditUpdatesForCollaboratorOnDate(
            $collaborator,
            $date,
            [self::AUDIT_DOCTOR_NURSE_UPDATED, self::AUDIT_DOCTOR_NURSE_DOCUMENT_UPLOADED],
            SupplierProviderSystemUpdateChartSeries::doctorNurseRelevantFieldNames(),
        );

        $related = self::countRelatedTableUpdates($collaborator, $date);

        return $juridicos + $naturales + $related;
    }

    /**
     * @param  list<string>  $actions
     * @param  list<string>  $relevantFields
     */
    private static function countAuditUpdatesForCollaboratorOnDate(string $collaborator, string $date, array $actions, array $relevantFields): int
    {
        $logs = Log::query()
            ->whereIn('action', $actions)
            ->whereDate('created_at', $date)
            ->get(['action', 'response', 'route']);

        $count = 0;

        foreach ($logs as $log) {
            $payload = json_decode((string) $log->response, true);

            if (! is_array($payload)) {
                continue;
            }

            $details = $payload['details'] ?? null;

            if (! is_array($details)) {
                continue;
            }

            if (! self::collaboratorMatches($collaborator, $details, $payload)) {
                continue;
            }

            if ($log->action === self::AUDIT_SUPPLIER_DOCUMENT_UPLOADED
                || $log->action === self::AUDIT_DOCTOR_NURSE_DOCUMENT_UPLOADED) {
                if (self::isCartaAcceptanceUpload($details, (string) $log->route)) {
                    continue;
                }

                $count++;

                continue;
            }

            $changedFields = $details['changed_fields'] ?? [];

            if (! is_array($changedFields) || $changedFields === []) {
                continue;
            }

            if (! self::hasRelevantFieldChange($changedFields, $relevantFields)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private static function countAuditCartaUploads(string $collaborator, string $date, string $action): int
    {
        $logs = Log::query()
            ->where('action', $action)
            ->whereDate('created_at', $date)
            ->get(['response', 'route']);

        $count = 0;

        foreach ($logs as $log) {
            $payload = json_decode((string) $log->response, true);

            if (! is_array($payload)) {
                continue;
            }

            $details = $payload['details'] ?? null;

            if (! is_array($details)) {
                continue;
            }

            if (! self::isCartaAcceptanceUpload($details, (string) $log->route)) {
                continue;
            }

            if (! self::collaboratorMatches($collaborator, $details, $payload)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private static function countRelatedTableUpdates(string $collaborator, string $date): int
    {
        return self::countRelatedTableForCollaboratorOnDate(SupplierContactPrincipal::query(), $collaborator, $date)
            + self::countRelatedTableForCollaboratorOnDate(SupplierZonaCobertura::query(), $collaborator, $date)
            + self::countRelatedTableForCollaboratorOnDate(SupplierRedGlobal::query(), $collaborator, $date);
    }

    /**
     * @param  Builder<SupplierContactPrincipal>|Builder<SupplierRedGlobal>|Builder<SupplierZonaCobertura>  $query
     */
    private static function countRelatedTableForCollaboratorOnDate(Builder $query, string $collaborator, string $date): int
    {
        return (int) $query
            ->whereColumn('updated_at', '>', 'created_at')
            ->whereDate('updated_at', $date)
            ->whereRaw('TRIM(updated_by) = ?', [$collaborator])
            ->count();
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $payload
     */
    private static function collaboratorMatches(string $collaborator, array $details, array $payload): bool
    {
        $candidates = [
            $details['updated_by'] ?? null,
            $details['created_by'] ?? null,
            $payload['user']['name'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            if (trim($candidate) === $collaborator) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function isCartaAcceptanceUpload(array $details, string $route): bool
    {
        if (str_contains($route, self::CARTA_ACCEPTANCE_ROUTE_FRAGMENT)) {
            return true;
        }

        return ($details['document_type'] ?? null) === self::DOCUMENT_TYPE_CARTA;
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
}
