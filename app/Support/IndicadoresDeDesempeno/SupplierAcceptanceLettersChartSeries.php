<?php

declare(strict_types=1);

namespace App\Support\IndicadoresDeDesempeno;

use App\Models\Log;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

final class SupplierAcceptanceLettersChartSeries
{
    public const TYPE_JURIDICOS = 'juridicos';

    public const TYPE_NATURALES = 'naturales';

    public const LABEL_JURIDICOS = 'Proveedores jurídicos';

    public const LABEL_NATURALES = 'Proveedores naturales';

    private const AUDIT_SUPPLIER_DOCUMENT_UPLOADED = 'AUDIT_OPERATIONS_SUPPLIER_DOCUMENT_UPLOADED';

    private const AUDIT_DOCTOR_NURSE_DOCUMENT_UPLOADED = 'AUDIT_OPERATIONS_DOCTOR_NURSE_DOCUMENT_UPLOADED';

    private const CARTA_ACCEPTANCE_ROUTE_FRAGMENT = 'carta-acceptance.upload';

    private const DOCUMENT_TYPE_CARTA = 'CARTA_ACEPTACION';

    /**
     * @return array{labels: list<string>, juridicos: list<int>, naturales: list<int>}
     */
    public static function groupedByCollaborator(?int $year = null, ?string $from = null, ?string $to = null): array
    {
        /** @var array<string, int> $juridicos */
        $juridicos = self::countsByCollaboratorForTipo(self::TYPE_JURIDICOS, $year, $from, $to);

        /** @var array<string, int> $naturales */
        $naturales = self::countsByCollaboratorForTipo(self::TYPE_NATURALES, $year, $from, $to);

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
    private static function countsByCollaboratorForTipo(string $tipo, ?int $year, ?string $from = null, ?string $to = null): array
    {
        $logs = Log::query()
            ->where('action', self::auditActionForTipo($tipo))
            ->tap(fn (Builder $query): Builder => IndicadoresDeDesempenoPeriodFilter::apply($query, 'created_at', $year, $from, $to))
            ->get(['response', 'route']);

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

            if (! self::isCartaAcceptanceUpload($details, (string) $log->route)) {
                continue;
            }

            $collaborator = self::resolveCollaboratorName($details, $payload);

            if ($collaborator === null) {
                continue;
            }

            $counts[$collaborator] = ($counts[$collaborator] ?? 0) + 1;
        }

        return $counts;
    }

    private static function auditActionForTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TYPE_JURIDICOS => self::AUDIT_SUPPLIER_DOCUMENT_UPLOADED,
            self::TYPE_NATURALES => self::AUDIT_DOCTOR_NURSE_DOCUMENT_UPLOADED,
            default => throw new InvalidArgumentException("Tipo de proveedor inválido: {$tipo}"),
        };
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
}
