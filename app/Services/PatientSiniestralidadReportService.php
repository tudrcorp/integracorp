<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatient;
use App\Support\CsvExportStream;
use App\Support\Telemedicine\TelemedicinePatientDisplayName;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PatientSiniestralidadReportService
{
    public const DEFAULT_TOP_N = 50;

    public const MIN_TOP_N = 1;

    public const MAX_TOP_N = 500;

    private const CACHE_PREFIX = 'telemedicine_patient_siniestralidad_report_';

    private const TOKEN_TTL_SECONDS = 300;

    private const STATUS_FINALIZED = 'FINALIZADO';

    /**
     * @param  array{top_n?: int|string|null, date_from?: string|null, date_to?: string|null}  $params
     */
    public static function storeParamsAndGetToken(array $params): string
    {
        $token = bin2hex(random_bytes(16));
        Cache::put(self::CACHE_PREFIX.$token, self::normalizeParams($params), self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{top_n: int, date_from: string|null, date_to: string|null}|null
     */
    public static function pullParamsFromToken(string $token): ?array
    {
        $payload = Cache::get(self::CACHE_PREFIX.$token);

        if (! is_array($payload)) {
            return null;
        }

        return self::normalizeParams($payload);
    }

    /**
     * @param  array{top_n?: int|string|null, date_from?: string|null, date_to?: string|null}  $params
     * @return array{top_n: int, date_from: string|null, date_to: string|null}
     */
    public static function normalizeParams(array $params): array
    {
        $topN = (int) ($params['top_n'] ?? self::DEFAULT_TOP_N);
        $topN = max(self::MIN_TOP_N, min(self::MAX_TOP_N, $topN));

        $dateFrom = filled($params['date_from'] ?? null) ? (string) $params['date_from'] : null;
        $dateTo = filled($params['date_to'] ?? null) ? (string) $params['date_to'] : null;

        return [
            'top_n' => $topN,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * @param  array{top_n: int, date_from: string|null, date_to: string|null}  $params
     * @return array{
     *     params: array{top_n: int, date_from: string|null, date_to: string|null},
     *     rows: list<array{
     *         rank: int,
     *         telemedicine_patient_id: int,
     *         patient: string,
     *         document: string,
     *         code: string,
     *         type_affiliation: string,
     *         business_unit: string,
     *         claims_count: int,
     *         total_bill_price: float
     *     }>,
     *     top_rows: list<array{
     *         rank: int,
     *         telemedicine_patient_id: int,
     *         patient: string,
     *         document: string,
     *         code: string,
     *         type_affiliation: string,
     *         business_unit: string,
     *         claims_count: int,
     *         total_bill_price: float
     *     }>,
     *     totals: array{patients: int, claims: int, bill_price: float}
     * }
     */
    public static function build(array $params): array
    {
        $params = self::normalizeParams($params);
        $rows = self::aggregateRows($params);

        $topRows = array_slice($rows, 0, $params['top_n']);

        return [
            'params' => $params,
            'rows' => $rows,
            'top_rows' => $topRows,
            'totals' => [
                'patients' => count($rows),
                'claims' => (int) array_sum(array_column($rows, 'claims_count')),
                'bill_price' => round((float) array_sum(array_column($rows, 'total_bill_price')), 2),
            ],
        ];
    }

    /**
     * @param  array{top_n: int, date_from: string|null, date_to: string|null}  $params
     */
    public static function makePdf(array $params): PdfDocument
    {
        $report = self::build($params);

        return Pdf::loadView('documents.patient-siniestralidad-report', [
            'rows' => $report['rows'],
            'topRows' => $report['top_rows'],
            'totals' => $report['totals'],
            'params' => $report['params'],
            'generatedAt' => now(),
            'logoDataUri' => self::logoDataUri(),
        ])->setPaper('a4', 'landscape');
    }

    /**
     * @param  array{top_n: int, date_from: string|null, date_to: string|null}  $params
     */
    public static function streamCsv(array $params): StreamedResponse
    {
        $report = self::build($params);
        $filename = self::csvFilename($params['top_n']);

        return new StreamedResponse(function () use ($report): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Sección',
                'Posición',
                'Paciente',
                'Identificación',
                'Código',
                'Tipo afiliación',
                'Unidad de negocio',
                'Cantidad siniestros (FINALIZADO)',
                'Monto total factura (USD)',
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, self::csvRow('Siniestralidad por paciente', $row));
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'Top '.$report['params']['top_n'].' pacientes más siniestrosos',
            ]);

            foreach ($report['top_rows'] as $row) {
                fputcsv($handle, self::csvRow('Top '.$report['params']['top_n'], $row));
            }

            fputcsv($handle, []);
            fputcsv($handle, [
                'TOTALES',
                '',
                '',
                '',
                '',
                '',
                '',
                (string) $report['totals']['claims'],
                number_format($report['totals']['bill_price'], 2, '.', ''),
            ]);

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public static function pdfFilename(int $topN): string
    {
        return 'reporte-siniestralidad-pacientes-top'.$topN.'-'.now()->format('Y-m-d_His').'.pdf';
    }

    public static function csvFilename(int $topN): string
    {
        return 'reporte-siniestralidad-pacientes-top'.$topN.'-'.now()->format('Y-m-d_His').'.csv';
    }

    /**
     * @param  array{top_n: int, date_from: string|null, date_to: string|null}  $params
     * @return list<array{
     *     rank: int,
     *     telemedicine_patient_id: int,
     *     patient: string,
     *     document: string,
     *     code: string,
     *     type_affiliation: string,
     *     business_unit: string,
     *     claims_count: int,
     *     total_bill_price: float
     * }>
     */
    private static function aggregateRows(array $params): array
    {
        $query = OperationCoordinationService::query()
            ->selectRaw('telemedicine_patient_id')
            ->selectRaw('COUNT(*) as claims_count')
            ->selectRaw('COALESCE(SUM(bill_price), 0) as total_bill_price')
            ->where('status', self::STATUS_FINALIZED)
            ->whereNotNull('telemedicine_patient_id')
            ->groupBy('telemedicine_patient_id')
            ->orderByDesc('claims_count')
            ->orderByDesc('total_bill_price');

        if ($params['date_from'] !== null) {
            $query->whereDate('created_at', '>=', $params['date_from']);
        }

        if ($params['date_to'] !== null) {
            $query->whereDate('created_at', '<=', $params['date_to']);
        }

        /** @var Collection<int, object{telemedicine_patient_id: mixed, claims_count: mixed, total_bill_price: mixed}> $aggregates */
        $aggregates = $query->get();

        if ($aggregates->isEmpty()) {
            return [];
        }

        $patientIds = $aggregates
            ->pluck('telemedicine_patient_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $patients = TelemedicinePatient::query()
            ->with(['businessUnit:id,definition'])
            ->whereIn('id', $patientIds)
            ->get()
            ->keyBy('id');

        $normalizedAggregates = $aggregates
            ->map(fn (object $aggregate): array => [
                'telemedicine_patient_id' => (int) $aggregate->telemedicine_patient_id,
                'claims_count' => (int) $aggregate->claims_count,
                'total_bill_price' => round((float) $aggregate->total_bill_price, 2),
            ])
            ->all();

        $patientsById = [];
        foreach ($patients as $id => $patient) {
            $patientsById[(int) $id] = [
                'full_name' => TelemedicinePatientDisplayName::fromPatient($patient) ?: $patient->full_name,
                'nro_identificacion' => $patient->nro_identificacion,
                'code' => $patient->code,
                'type_affiliation' => $patient->type_affiliation,
                'business_unit' => $patient->businessUnit?->definition,
            ];
        }

        return self::mapAggregatesToRankedRows($normalizedAggregates, $patientsById);
    }

    /**
     * @param  list<array{telemedicine_patient_id: int, claims_count: int, total_bill_price: float}>  $aggregates
     * @param  array<int, array{full_name: mixed, nro_identificacion: mixed, code: mixed, type_affiliation: mixed, business_unit: mixed}>  $patientsById
     * @return list<array{
     *     rank: int,
     *     telemedicine_patient_id: int,
     *     patient: string,
     *     document: string,
     *     code: string,
     *     type_affiliation: string,
     *     business_unit: string,
     *     claims_count: int,
     *     total_bill_price: float
     * }>
     */
    public static function mapAggregatesToRankedRows(array $aggregates, array $patientsById): array
    {
        usort($aggregates, function (array $left, array $right): int {
            $byClaims = $right['claims_count'] <=> $left['claims_count'];

            if ($byClaims !== 0) {
                return $byClaims;
            }

            return $right['total_bill_price'] <=> $left['total_bill_price'];
        });

        $rows = [];
        $rank = 1;

        foreach ($aggregates as $aggregate) {
            $patientId = (int) $aggregate['telemedicine_patient_id'];
            $patient = $patientsById[$patientId] ?? [];

            $rows[] = [
                'rank' => $rank,
                'telemedicine_patient_id' => $patientId,
                'patient' => filled($patient['full_name'] ?? null)
                    ? mb_strtoupper(trim((string) $patient['full_name']))
                    : 'PACIENTE #'.$patientId,
                'document' => filled($patient['nro_identificacion'] ?? null)
                    ? (string) $patient['nro_identificacion']
                    : '—',
                'code' => filled($patient['code'] ?? null) ? (string) $patient['code'] : '—',
                'type_affiliation' => filled($patient['type_affiliation'] ?? null)
                    ? (string) $patient['type_affiliation']
                    : '—',
                'business_unit' => filled($patient['business_unit'] ?? null)
                    ? (string) $patient['business_unit']
                    : '—',
                'claims_count' => (int) $aggregate['claims_count'],
                'total_bill_price' => round((float) $aggregate['total_bill_price'], 2),
            ];

            $rank++;
        }

        return $rows;
    }

    /**
     * @param  array{
     *     rank: int,
     *     telemedicine_patient_id: int,
     *     patient: string,
     *     document: string,
     *     code: string,
     *     type_affiliation: string,
     *     business_unit: string,
     *     claims_count: int,
     *     total_bill_price: float
     * }  $row
     * @return list<string>
     */
    private static function csvRow(string $section, array $row): array
    {
        return [
            $section,
            (string) $row['rank'],
            $row['patient'],
            $row['document'],
            $row['code'],
            $row['type_affiliation'],
            $row['business_unit'],
            (string) $row['claims_count'],
            number_format($row['total_bill_price'], 2, '.', ''),
        ];
    }

    private static function logoDataUri(): string
    {
        $logoPath = public_path('image/logoNewPdf.png');

        if (! is_file($logoPath)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
    }
}
