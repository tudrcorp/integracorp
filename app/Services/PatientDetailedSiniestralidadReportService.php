<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatient;
use App\Support\Charts\SvgLineChartRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class PatientDetailedSiniestralidadReportService
{
    private const STATUS_FINALIZED = 'FINALIZADO';

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
     * @return array{
     *     patient: array{
     *         id: int,
     *         full_name: string,
     *         nro_identificacion: string,
     *         code: string,
     *         type_affiliation: string,
     *         business_unit: string,
     *         phone: string,
     *         email: string
     *     },
     *     year: int,
     *     through_month: int,
     *     summary: array{services_count: int, total_bill_price: float},
     *     year_series: array{year: int, labels: list<string>, values: list<int>},
     *     chart_data_uri: string,
     *     services: list<array{
     *         id: int,
     *         reference_number: string,
     *         date_solicitud: string,
     *         date_service: string,
     *         created_at: string,
     *         specific_service: string,
     *         servicie: string,
     *         status: string,
     *         bill_price: float,
     *         bill_number: string
     *     }>
     * }
     */
    public static function build(TelemedicinePatient $patient, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $year = (int) $asOf->year;
        $throughMonth = (int) $asOf->month;

        $patient->loadMissing(['businessUnit:id,definition']);

        $services = OperationCoordinationService::query()
            ->where('telemedicine_patient_id', $patient->id)
            ->where('status', self::STATUS_FINALIZED)
            ->orderByDesc('created_at')
            ->get();

        $yearSeries = self::buildYearSeries($services, $year, $throughMonth);

        $serviceRows = $services
            ->map(fn (OperationCoordinationService $service): array => [
                'id' => (int) $service->id,
                'reference_number' => filled($service->reference_number) ? (string) $service->reference_number : '—',
                'date_solicitud' => filled($service->date_solicitud) ? (string) $service->date_solicitud : '—',
                'date_service' => filled($service->date_service) ? (string) $service->date_service : '—',
                'created_at' => $service->created_at?->format('d/m/Y H:i') ?? '—',
                'specific_service' => filled($service->specific_service) ? (string) $service->specific_service : '—',
                'servicie' => filled($service->servicie) ? (string) $service->servicie : '—',
                'status' => (string) ($service->status ?? '—'),
                'bill_price' => round((float) ($service->bill_price ?? 0), 2),
                'bill_number' => filled($service->bill_number) ? (string) $service->bill_number : '—',
            ])
            ->values()
            ->all();

        return [
            'patient' => [
                'id' => (int) $patient->id,
                'full_name' => filled($patient->full_name)
                    ? mb_strtoupper(trim((string) $patient->full_name))
                    : 'PACIENTE #'.$patient->id,
                'nro_identificacion' => filled($patient->nro_identificacion) ? (string) $patient->nro_identificacion : '—',
                'code' => filled($patient->code) ? (string) $patient->code : '—',
                'type_affiliation' => filled($patient->type_affiliation) ? (string) $patient->type_affiliation : '—',
                'business_unit' => filled($patient->businessUnit?->definition)
                    ? (string) $patient->businessUnit->definition
                    : '—',
                'phone' => filled($patient->phone) ? (string) $patient->phone : '—',
                'email' => filled($patient->email) ? (string) $patient->email : '—',
            ],
            'year' => $year,
            'through_month' => $throughMonth,
            'summary' => [
                'services_count' => count($serviceRows),
                'total_bill_price' => round((float) array_sum(array_column($serviceRows, 'bill_price')), 2),
            ],
            'year_series' => $yearSeries,
            'chart_data_uri' => SvgLineChartRenderer::toPngDataUri(
                $yearSeries['labels'],
                $yearSeries['values'],
                'Comportamiento '.$year.': servicios FINALIZADO por mes',
            ),
            'services' => $serviceRows,
        ];
    }

    public static function makePdf(TelemedicinePatient $patient, ?Carbon $asOf = null): PdfDocument
    {
        $report = self::build($patient, $asOf);

        return Pdf::loadView('documents.patient-detailed-siniestralidad-report', [
            'report' => $report,
            'generatedAt' => now(),
            'logoDataUri' => self::logoDataUri(),
        ])->setPaper('a4', 'portrait');
    }

    public static function filename(TelemedicinePatient $patient): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($patient->code ?: $patient->id)) ?: (string) $patient->id;

        return 'reporte-detallado-paciente-'.$slug.'-'.now()->format('Y-m-d_His').'.pdf';
    }

    /**
     * @param  Collection<int, OperationCoordinationService>  $services
     * @return array{year: int, labels: list<string>, values: list<int>}
     */
    public static function buildYearSeries(Collection $services, int $year, int $throughMonth): array
    {
        $throughMonth = max(1, min(12, $throughMonth));
        $buckets = array_fill(1, $throughMonth, 0);

        foreach ($services as $service) {
            if (mb_strtoupper(trim((string) ($service->status ?? ''))) !== self::STATUS_FINALIZED) {
                continue;
            }

            $createdAt = $service->created_at;
            if ($createdAt === null) {
                continue;
            }

            if ((int) $createdAt->year !== $year) {
                continue;
            }

            $month = (int) $createdAt->month;
            if ($month < 1 || $month > $throughMonth) {
                continue;
            }

            $buckets[$month]++;
        }

        $labels = [];
        $values = [];
        for ($month = 1; $month <= $throughMonth; $month++) {
            $labels[] = self::MONTH_LABELS_ES[$month];
            $values[] = (int) $buckets[$month];
        }

        return [
            'year' => $year,
            'labels' => $labels,
            'values' => $values,
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
