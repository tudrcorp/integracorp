<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Agent;
use App\Support\CommercialStructure\AgentHierarchyCommissionResolver;
use App\Support\CommercialStructureBankingExportColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdministrationAgentReportsExportService
{
    public const REPORT_GEO_SUMMARY = 'geo_summary';

    public const REPORT_COMMISSION_PERCENTAGES = 'commission_percentages';

    public const REPORT_COMMISSION_HIERARCHY = 'commission_hierarchy';

    public const REPORT_AGENT_STATUS = 'agent_status';

    /**
     * @return array<string, string>
     */
    public static function reportLabels(): array
    {
        return [
            self::REPORT_GEO_SUMMARY => 'Reporte de agentes por estado, región y ciudad',
            self::REPORT_COMMISSION_PERCENTAGES => 'Reporte de porcentaje de comisiones',
            self::REPORT_COMMISSION_HIERARCHY => 'Reporte de comisiones por jerarquía',
            self::REPORT_AGENT_STATUS => 'Reporte de agentes por estatus',
        ];
    }

    public static function toCsv(string $report): StreamedResponse
    {
        $filename = self::buildFilename($report, 'csv');

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            foreach (self::rowsForReport($report) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public static function toXlsx(string $report): BinaryFileResponse
    {
        $filename = self::buildFilename($report, 'xlsx');
        $path = tempnam(sys_get_temp_dir(), 'agent_report_');

        if ($path === false) {
            abort(500, 'No se pudo preparar el archivo temporal.');
        }

        $path .= '.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);

        foreach (self::rowsForReport($report) as $row) {
            $writer->addRow(Row::fromValues(array_map(
                static fn (mixed $v): string|int|float|null => self::normalizeCellValue($v),
                $row,
            )));
        }

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private static function buildFilename(string $report, string $extension): string
    {
        $slug = match ($report) {
            self::REPORT_GEO_SUMMARY => 'ubicacion_estado_region_ciudad',
            self::REPORT_COMMISSION_PERCENTAGES => 'comisiones',
            self::REPORT_COMMISSION_HIERARCHY => 'comisiones_jerarquia',
            self::REPORT_AGENT_STATUS => 'estatus',
            default => 'reporte',
        };

        return 'reporte_agentes_'.$slug.'_'.now()->format('Y-m-d_His').'.'.$extension;
    }

    /**
     * @return iterable<int, array<int, scalar|null>>
     */
    private static function rowsForReport(string $report): iterable
    {
        return match ($report) {
            self::REPORT_GEO_SUMMARY => self::geoSummaryRows(),
            self::REPORT_COMMISSION_PERCENTAGES => self::commissionPercentageRows(),
            self::REPORT_COMMISSION_HIERARCHY => self::commissionHierarchyRows(),
            self::REPORT_AGENT_STATUS => self::agentStatusRows(),
            default => [],
        };
    }

    /**
     * @return iterable<int, array<int, scalar|null>>
     */
    private static function commissionPercentageRows(): iterable
    {
        yield array_merge([
            'Código',
            'Nombre',
            'RIF',
            'Estatus',
            '% TDEC',
            '% TDEC renovación',
            '% TDEV',
            '% TDEV renovación',
            'TDEC (producto)',
            'TDEV (producto)',
        ], CommercialStructureBankingExportColumns::csvHeaders());

        $query = self::scopedAgentQuery()->orderBy('id');

        foreach ($query->cursor() as $agent) {
            /** @var Agent $agent */
            yield array_merge([
                self::agentDisplayCode($agent),
                (string) ($agent->name ?? ''),
                (string) ($agent->rif ?? ''),
                (string) ($agent->status ?? ''),
                self::nullableNumber($agent->commission_tdec),
                self::nullableNumber($agent->commission_tdec_renewal),
                self::nullableNumber($agent->commission_tdev),
                self::nullableNumber($agent->commission_tdev_renewal),
                self::boolishLabel($agent->tdec),
                self::boolishLabel($agent->tdev),
            ], CommercialStructureBankingExportColumns::valuesFromModel($agent));
        }
    }

    private static function agentDisplayCode(Agent $agent): string
    {
        $code = trim((string) ($agent->code_agent ?? ''));

        if ($code !== '') {
            return $code;
        }

        return 'AGT-000'.(string) $agent->id;
    }

    /**
     * @return iterable<int, array<int, scalar|null>>
     */
    private static function commissionHierarchyRows(): iterable
    {
        yield [
            'Código agente referencia',
            'Nombre agente referencia',
            'Orden jerarquía',
            'Rol en jerarquía',
            'Tipo integrante',
            'Código integrante',
            'Nombre integrante',
            'Estatus integrante',
            'Cadena jerárquica',
            '% TDEC',
            '% TDEC renovación',
            '% TDEV',
            '% TDEV renovación',
            'Advertencias jerarquía',
        ];

        $query = self::scopedAgentQuery()->orderBy('id');

        foreach ($query->cursor() as $agent) {
            /** @var Agent $agent */
            $resolution = AgentHierarchyCommissionResolver::resolve($agent);
            $nodes = $resolution['nodes'];
            $warnings = $resolution['warnings'];
            $linearChain = AgentHierarchyCommissionResolver::formatLinearChain($nodes);
            $warningsText = implode(' | ', $warnings);
            $referenceCode = self::agentDisplayCode($agent);
            $referenceName = (string) ($agent->name ?? '');
            $totalNodes = count($nodes);

            foreach ($nodes as $index => $node) {
                $order = $index + 1;

                yield [
                    $referenceCode,
                    $referenceName,
                    $order,
                    (string) ($node['role'] ?? ''),
                    (string) ($node['entity_type'] ?? ''),
                    (string) ($node['code'] ?? ''),
                    (string) ($node['name'] ?? ''),
                    (string) ($node['status'] ?? ''),
                    $linearChain,
                    self::formatCommissionPercent($node['commission_tdec'] ?? null),
                    self::formatCommissionPercent($node['commission_tdec_renewal'] ?? null),
                    self::formatCommissionPercent($node['commission_tdev'] ?? null),
                    self::formatCommissionPercent($node['commission_tdev_renewal'] ?? null),
                    $order === 1 ? $warningsText : '',
                ];
            }

            if ($totalNodes === 0) {
                yield [
                    $referenceCode,
                    $referenceName,
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    $warningsText !== '' ? $warningsText : 'Sin integrantes resueltos en la jerarquía.',
                ];
            }
        }
    }

    private static function formatCommissionPercent(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            return '';
        }

        return number_format((float) $value, 2, ',', '.');
    }

    /**
     * @return iterable<int, array<int, scalar|null>>
     */
    private static function geoSummaryRows(): iterable
    {
        yield ['Estado', 'Región', 'Ciudad', 'Total de agentes'];

        $rows = self::scopedAgentQuery()
            ->leftJoin('states', 'agents.state_id', '=', 'states.id')
            ->leftJoin('regions', 'states.region_id', '=', 'regions.id')
            ->leftJoin('cities', 'agents.city_id', '=', 'cities.id')
            ->selectRaw("COALESCE(states.definition, '(Sin estado)') as estado")
            ->selectRaw("COALESCE(regions.definition, '(Sin región)') as region")
            ->selectRaw("COALESCE(cities.definition, '(Sin ciudad)') as ciudad")
            ->selectRaw('COUNT(agents.id) as total')
            ->groupByRaw("COALESCE(states.definition, '(Sin estado)'), COALESCE(regions.definition, '(Sin región)'), COALESCE(cities.definition, '(Sin ciudad)')")
            ->orderByDesc('total')
            ->orderBy('estado')
            ->get();

        foreach ($rows as $row) {
            yield [
                (string) $row->estado,
                (string) $row->region,
                (string) $row->ciudad,
                (int) $row->total,
            ];
        }
    }

    /**
     * @return iterable<int, array<int, scalar|null>>
     */
    private static function agentStatusRows(): iterable
    {
        yield ['Estatus', 'Total de agentes'];

        $rows = self::scopedAgentQuery()
            ->selectRaw("COALESCE(agents.status, '(Sin estatus)') as estatus")
            ->selectRaw('COUNT(agents.id) as total')
            ->groupByRaw("COALESCE(agents.status, '(Sin estatus)')")
            ->orderByDesc('total')
            ->orderBy('estatus')
            ->get();

        foreach ($rows as $row) {
            yield [
                (string) $row->estatus,
                (int) $row->total,
            ];
        }
    }

    private static function scopedAgentQuery(): Builder
    {
        $query = Agent::query();

        $user = Auth::user();

        if ($user !== null && ! empty($user->is_accountManagers)) {
            $query->where($query->qualifyColumn('ownerAccountManagers'), $user->id);
        }

        return $query;
    }

    private static function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private static function boolishLabel(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }

        $s = Str::lower(trim((string) $value));

        return match ($s) {
            '1', 'true', 'si', 'sí', 'yes' => 'Sí',
            '0', 'false', 'no' => 'No',
            default => (string) $value,
        };
    }

    private static function normalizeCellValue(mixed $value): string|int|float|null
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return (string) $value;
    }
}
