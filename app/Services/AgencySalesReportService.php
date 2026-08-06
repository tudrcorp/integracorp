<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Sale;
use App\Support\Charts\SvgDualLineChartRenderer;
use App\Support\CsvExportStream;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AgencySalesReportService
{
    private const CACHE_PREFIX = 'agency_sales_report_';

    private const TOKEN_TTL_SECONDS = 300;

    private const MONTH_LABELS_ES = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic',
    ];

    /**
     * @param  array{agency_id?: int|string, period?: string, date_from?: mixed, date_to?: mixed, format?: string}  $params
     */
    public static function storeParamsAndGetToken(array $params): string
    {
        $token = bin2hex(random_bytes(16));
        Cache::put(self::CACHE_PREFIX.$token, self::normalizeDownloadParams($params), self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{agency_id: int, period: string, date_from: string|null, date_to: string|null, format: string}|null
     */
    public static function pullParamsFromToken(string $token): ?array
    {
        $payload = Cache::pull(self::CACHE_PREFIX.$token);

        if (! is_array($payload)) {
            return null;
        }

        return self::normalizeDownloadParams($payload);
    }

    /**
     * @param  array{agency_id?: int|string, period?: string, date_from?: mixed, date_to?: mixed, format?: string}  $params
     * @return array{agency_id: int, period: string, date_from: string|null, date_to: string|null, format: string}
     */
    public static function normalizeDownloadParams(array $params): array
    {
        $format = (string) ($params['format'] ?? 'pdf');
        if (! in_array($format, ['pdf', 'csv'], true)) {
            $format = 'pdf';
        }

        $period = (string) ($params['period'] ?? 'current_year');
        if (! in_array($period, ['current_year', 'range'], true)) {
            $period = 'current_year';
        }

        return [
            'agency_id' => (int) ($params['agency_id'] ?? 0),
            'period' => $period,
            'date_from' => filled($params['date_from'] ?? null) ? (string) $params['date_from'] : null,
            'date_to' => filled($params['date_to'] ?? null) ? (string) $params['date_to'] : null,
            'format' => $format,
        ];
    }

    /**
     * @param  array{period?: string, date_from?: mixed, date_to?: mixed, format?: string}  $data
     * @return array{from: Carbon, to: Carbon, period_label: string}
     */
    public static function resolvePeriod(array $data, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $period = (string) ($data['period'] ?? 'current_year');

        if ($period === 'range') {
            $from = filled($data['date_from'] ?? null)
                ? Carbon::parse((string) $data['date_from'])->startOfDay()
                : $asOf->copy()->startOfYear();
            $to = filled($data['date_to'] ?? null)
                ? Carbon::parse((string) $data['date_to'])->endOfDay()
                : $asOf->copy()->endOfDay();

            if ($from->greaterThan($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            return [
                'from' => $from,
                'to' => $to,
                'period_label' => $from->format('d/m/Y').' — '.$to->format('d/m/Y'),
            ];
        }

        $from = $asOf->copy()->startOfYear();
        $to = $asOf->copy()->endOfDay();

        return [
            'from' => $from,
            'to' => $to,
            'period_label' => 'Año en curso ('.$asOf->year.')',
        ];
    }

    /**
     * @return array{
     *     agency: array{code: string, name: string},
     *     period_label: string,
     *     from: string,
     *     to: string,
     *     year: int,
     *     summary: array{
     *         individual_count: int,
     *         corporate_count: int,
     *         individual_population: int,
     *         corporate_population: int,
     *         individual_total: float,
     *         corporate_total: float,
     *         grand_total: float
     *     },
     *     rows: list<array{
     *         date: string,
     *         agency_name: string,
     *         type: string,
     *         plan: string,
     *         population: string,
     *         total_amount: float
     *     }>,
     *     year_series: array{
     *         year: int,
     *         labels: list<string>,
     *         individual: list<float>,
     *         corporate: list<float>
     *     },
     *     chart_data_uri: string
     * }
     */
    public static function build(Agency $agency, Carbon $from, Carbon $to, string $periodLabel, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $agencyCode = trim((string) ($agency->code ?? ''));
        $agencyName = filled($agency->name_corporative)
            ? self::safeUtf8((string) $agency->name_corporative)
            : ($agencyCode !== '' ? $agencyCode : 'Agencia #'.$agency->getKey());

        $sales = Sale::query()
            ->with(['plan:id,description'])
            ->where('code_agency', $agencyCode)
            ->whereDate('created_at', '>=', $from->toDateString())
            ->whereDate('created_at', '<=', $to->toDateString())
            ->orderBy('created_at')
            ->get();

        $rows = [];

        foreach ($sales as $sale) {
            /** @var Sale $sale */
            $isCorporate = self::isCorporateSale($sale->type);

            $rows[] = [
                'date' => $sale->created_at?->format('d/m/Y') ?? '—',
                'agency_name' => $agencyName,
                'type' => $isCorporate ? 'Corporativa' : 'Individual',
                'plan' => filled($sale->plan?->description)
                    ? self::safeUtf8((string) $sale->plan->description)
                    : '—',
                'population' => self::formatPopulation($sale->persons, default: $isCorporate ? 0 : 1),
                'total_amount' => round((float) ($sale->total_amount ?? 0), 2),
            ];
        }

        $individualTotal = round((float) collect($rows)->where('type', 'Individual')->sum('total_amount'), 2);
        $corporateTotal = round((float) collect($rows)->where('type', 'Corporativa')->sum('total_amount'), 2);

        $yearSeries = self::buildCurrentYearSeries($agencyCode, $asOf);

        $individualActive = self::activeIndividualAffiliationStats($agencyCode);
        $corporateActive = self::activeCorporateAffiliationStats($agencyCode);

        return [
            'agency' => [
                'code' => $agencyCode !== '' ? $agencyCode : '—',
                'name' => $agencyName,
            ],
            'period_label' => $periodLabel,
            'from' => $from->format('d/m/Y'),
            'to' => $to->format('d/m/Y'),
            'year' => (int) $asOf->year,
            'summary' => [
                'individual_count' => $individualActive['count'],
                'corporate_count' => $corporateActive['count'],
                'individual_population' => $individualActive['population'],
                'corporate_population' => $corporateActive['population'],
                'individual_total' => $individualTotal,
                'corporate_total' => $corporateTotal,
                'grand_total' => round($individualTotal + $corporateTotal, 2),
            ],
            'rows' => array_values($rows),
            'year_series' => $yearSeries,
            'chart_data_uri' => SvgDualLineChartRenderer::toPngDataUri(
                $yearSeries['labels'],
                $yearSeries['individual'],
                $yearSeries['corporate'],
                'Comportamiento de ventas '.$asOf->year.' (US$ total_amount)',
                'Individual',
                'Corporativo',
            ),
        ];
    }

    /**
     * @return array{year: int, labels: list<string>, individual: list<float>, corporate: list<float>}
     */
    public static function buildCurrentYearSeries(string $agencyCode, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $year = (int) $asOf->year;
        $throughMonth = (int) $asOf->month;

        $individualBuckets = array_fill(1, $throughMonth, 0.0);
        $corporateBuckets = array_fill(1, $throughMonth, 0.0);

        $sales = Sale::query()
            ->where('code_agency', $agencyCode)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', '<=', $throughMonth)
            ->get(['type', 'total_amount', 'created_at']);

        foreach ($sales as $sale) {
            $month = (int) ($sale->created_at?->month ?? 0);
            if ($month < 1 || $month > $throughMonth) {
                continue;
            }

            $amount = (float) ($sale->total_amount ?? 0);

            if (self::isCorporateSale($sale->type)) {
                $corporateBuckets[$month] += $amount;
            } else {
                $individualBuckets[$month] += $amount;
            }
        }

        $labels = [];
        $individual = [];
        $corporate = [];

        for ($month = 1; $month <= $throughMonth; $month++) {
            $labels[] = self::MONTH_LABELS_ES[$month];
            $individual[] = round($individualBuckets[$month], 2);
            $corporate[] = round($corporateBuckets[$month], 2);
        }

        return [
            'year' => $year,
            'labels' => $labels,
            'individual' => $individual,
            'corporate' => $corporate,
        ];
    }

    /**
     * @param  array{period?: string, date_from?: mixed, date_to?: mixed, format?: string}  $data
     */
    public static function download(Agency $agency, array $data): Response|StreamedResponse
    {
        $period = self::resolvePeriod($data);
        $report = self::build($agency, $period['from'], $period['to'], $period['period_label']);
        $format = (string) ($data['format'] ?? 'pdf');

        if ($format === 'csv') {
            return self::toCsv($agency, $report);
        }

        return self::toPdfDownload($agency, $report);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function toCsv(Agency $agency, array $report): StreamedResponse
    {
        $filename = self::filename($agency, 'csv');

        return response()->streamDownload(function () use ($report): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Fecha',
                'Agencia',
                'Tipo',
                'Plan',
                'Población',
                'Total venta US$',
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['date'],
                    $row['agency_name'],
                    $row['type'],
                    $row['plan'],
                    $row['population'],
                    number_format((float) $row['total_amount'], 2, '.', ''),
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'RESUMEN',
                'Afiliaciones activas individuales: '.$report['summary']['individual_count']
                    .' (población: '.$report['summary']['individual_population'].')',
                'Afiliaciones activas corporativas: '.$report['summary']['corporate_count']
                    .' (población: '.$report['summary']['corporate_population'].')',
                '',
                '',
                number_format((float) $report['summary']['grand_total'], 2, '.', ''),
            ]);
            fputcsv($handle, [
                '',
                'Venta individual US$: '.number_format((float) $report['summary']['individual_total'], 2, '.', ''),
                'Venta corporativa US$: '.number_format((float) $report['summary']['corporate_total'], 2, '.', ''),
                '',
                '',
                '',
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function makePdf(array $report): PdfDocument
    {
        return Pdf::loadView('documents.agency-sales-report', [
            'report' => $report,
            'generatedAt' => now(),
            'logoDataUri' => AgencyFichaPdfService::logoDataUri(),
        ])->setPaper('a4', 'landscape');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function toPdfDownload(Agency $agency, array $report): Response
    {
        $pdf = self::makePdf($report);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.self::filename($agency, 'pdf').'"',
        ]);
    }

    public static function filename(Agency $agency, string $extension): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($agency->code ?: $agency->getKey())) ?: 'agencia';

        return 'reporte-ventas-agencia-'.$slug.'-'.now()->format('Y-m-d_His').'.'.$extension;
    }

    /**
     * Serie mensual desde colecciones en memoria (útil en tests unitarios).
     *
     * @param  Collection<int, object{created_at: ?Carbon, total_amount: mixed}>  $individuals
     * @param  Collection<int, object{created_at: ?Carbon, total_amount: mixed}>  $corporates
     * @return array{year: int, labels: list<string>, individual: list<float>, corporate: list<float>}
     */
    public static function buildYearSeriesFromCollections(
        Collection $individuals,
        Collection $corporates,
        int $year,
        int $throughMonth,
    ): array {
        $throughMonth = max(1, min(12, $throughMonth));
        $individualBuckets = array_fill(1, $throughMonth, 0.0);
        $corporateBuckets = array_fill(1, $throughMonth, 0.0);

        foreach ($individuals as $row) {
            $createdAt = $row->created_at ?? null;
            if (! $createdAt instanceof Carbon || (int) $createdAt->year !== $year) {
                continue;
            }
            $month = (int) $createdAt->month;
            if ($month < 1 || $month > $throughMonth) {
                continue;
            }
            $individualBuckets[$month] += (float) ($row->total_amount ?? 0);
        }

        foreach ($corporates as $row) {
            $createdAt = $row->created_at ?? null;
            if (! $createdAt instanceof Carbon || (int) $createdAt->year !== $year) {
                continue;
            }
            $month = (int) $createdAt->month;
            if ($month < 1 || $month > $throughMonth) {
                continue;
            }
            $corporateBuckets[$month] += (float) ($row->total_amount ?? 0);
        }

        $labels = [];
        $individual = [];
        $corporate = [];

        for ($month = 1; $month <= $throughMonth; $month++) {
            $labels[] = self::MONTH_LABELS_ES[$month];
            $individual[] = round($individualBuckets[$month], 2);
            $corporate[] = round($corporateBuckets[$month], 2);
        }

        return [
            'year' => $year,
            'labels' => $labels,
            'individual' => $individual,
            'corporate' => $corporate,
        ];
    }

    public static function isCorporateSale(?string $type): bool
    {
        $normalized = mb_strtoupper(trim((string) $type));
        $normalized = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ä', 'Ë', 'Ï', 'Ö', 'Ü'],
            ['A', 'E', 'I', 'O', 'U', 'A', 'E', 'I', 'O', 'U'],
            $normalized,
        );

        return str_contains($normalized, 'CORPORATIV');
    }

    /**
     * @return array{count: int, population: int}
     */
    public static function activeIndividualAffiliationStats(string $agencyCode): array
    {
        if ($agencyCode === '') {
            return ['count' => 0, 'population' => 0];
        }

        $affiliations = Affiliation::query()
            ->where('code_agency', $agencyCode)
            ->where('status', 'ACTIVA')
            ->get(['family_members']);

        return [
            'count' => $affiliations->count(),
            'population' => (int) $affiliations->sum(
                fn (Affiliation $affiliation): int => self::numericPopulation($affiliation->family_members, default: 1),
            ),
        ];
    }

    /**
     * @return array{count: int, population: int}
     */
    public static function activeCorporateAffiliationStats(string $agencyCode): array
    {
        if ($agencyCode === '') {
            return ['count' => 0, 'population' => 0];
        }

        $affiliations = AffiliationCorporate::query()
            ->where('code_agency', $agencyCode)
            ->where('status', 'ACTIVA')
            ->get(['poblation']);

        return [
            'count' => $affiliations->count(),
            'population' => (int) $affiliations->sum(
                fn (AffiliationCorporate $affiliation): int => self::numericPopulation($affiliation->poblation, default: 0),
            ),
        ];
    }

    public static function countActiveIndividualAffiliations(string $agencyCode): int
    {
        return self::activeIndividualAffiliationStats($agencyCode)['count'];
    }

    public static function countActiveCorporateAffiliations(string $agencyCode): int
    {
        return self::activeCorporateAffiliationStats($agencyCode)['count'];
    }

    private static function numericPopulation(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $digits = preg_replace('/[^\d]/', '', (string) $value);

        if (is_string($digits) && $digits !== '') {
            return max(0, (int) $digits);
        }

        return $default;
    }

    private static function formatPopulation(mixed $value, int $default): string
    {
        if ($value === null || $value === '') {
            return (string) $default;
        }

        if (is_numeric($value)) {
            return (string) (int) $value;
        }

        return trim((string) $value) !== '' ? trim((string) $value) : (string) $default;
    }

    private static function safeUtf8(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        $fallback = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');

        return is_string($fallback) && $fallback !== '' ? $fallback : '—';
    }
}
