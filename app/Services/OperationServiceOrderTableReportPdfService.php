<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OperationServiceOrder;
use App\Support\Telemedicine\TelemedicinePatientDisplayName;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class OperationServiceOrderTableReportPdfService
{
    public const TYPE_BY_PATIENT = 'by-patient';

    public const TYPE_BY_SERVICE = 'by-service';

    private const CACHE_PREFIX = 'operation_service_order_table_report_';

    private const TOKEN_TTL_SECONDS = 300;

    /**
     * @param  array<int|string>  $ids
     */
    public static function storeIdsAndGetToken(array $ids): string
    {
        $ids = array_values(array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0,
        ));

        $token = bin2hex(random_bytes(16));
        Cache::put(self::CACHE_PREFIX.$token, ['ids' => $ids], self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * @return list<int>|null null = token inválido/expirado
     */
    public static function pullIdsFromToken(string $token): ?array
    {
        $payload = Cache::get(self::CACHE_PREFIX.$token);

        if (! is_array($payload) || ! array_key_exists('ids', $payload) || ! is_array($payload['ids'])) {
            return null;
        }

        return array_values(array_filter(
            array_map('intval', $payload['ids']),
            fn (int $id): bool => $id > 0,
        ));
    }

    public static function isValidType(string $type): bool
    {
        return in_array($type, [self::TYPE_BY_PATIENT, self::TYPE_BY_SERVICE], true);
    }

    public static function filename(string $type): string
    {
        $suffix = $type === self::TYPE_BY_SERVICE ? 'por-servicio' : 'por-paciente';

        return 'reporte-ordenes-servicio-'.$suffix.'-'.now()->format('Y-m-d_His').'.pdf';
    }

    /**
     * @param  list<int>  $ids
     */
    public static function make(string $type, array $ids): PdfDocument
    {
        $orders = self::loadOrders($ids);
        $logoDataUri = self::logoDataUri();
        $generatedAt = now();

        if ($type === self::TYPE_BY_SERVICE) {
            return Pdf::loadView('documents.operation-service-orders-by-service-report', [
                'rows' => self::buildByServiceRows($orders),
                'totalOrders' => $orders->count(),
                'generatedAt' => $generatedAt,
                'logoDataUri' => $logoDataUri,
            ])->setPaper('a4', 'portrait');
        }

        $groups = self::buildByPatientGroups($orders);

        return Pdf::loadView('documents.operation-service-orders-by-patient-report', [
            'groups' => $groups,
            'totalOrders' => $orders->count(),
            'totalPatients' => $groups->count(),
            'generatedAt' => $generatedAt,
            'logoDataUri' => $logoDataUri,
        ])->setPaper('a4', 'landscape');
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, OperationServiceOrder>
     */
    public static function loadOrders(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return OperationServiceOrder::query()
            ->with([
                'supplier:id,name',
                'telemedicineSupplier:id,name',
                'telemedicinePriority:id,name',
                'operationCoordinationService:id,patient,telemedicine_patient_id,telemedicine_case_id,ci_patient,reference_number',
                'operationCoordinationService.telemedicinePatient:id,full_name,nro_identificacion',
                'operationCoordinationService.telemedicineCase:id,code,patient_name',
            ])
            ->whereIn('id', $ids)
            ->orderByDesc('created_at')
            ->get();
    }

    public static function patientNameForOrder(OperationServiceOrder $record): string
    {
        $coordination = $record->operationCoordinationService;

        if ($coordination === null) {
            return 'Sin paciente';
        }

        $name = TelemedicinePatientDisplayName::forCoordination($coordination);

        return $name !== '—' ? $name : 'Sin paciente';
    }

    public static function patientDocumentForOrder(OperationServiceOrder $record): string
    {
        $coordination = $record->operationCoordinationService;

        if ($coordination === null) {
            return '—';
        }

        if (filled($coordination->ci_patient)) {
            return (string) $coordination->ci_patient;
        }

        if (filled($coordination->telemedicinePatient?->nro_identificacion)) {
            return (string) $coordination->telemedicinePatient->nro_identificacion;
        }

        return '—';
    }

    public static function supplierLabel(OperationServiceOrder $record): string
    {
        if (filled($record->supplier?->name)) {
            return (string) $record->supplier->name;
        }

        if (filled($record->supplier_external)) {
            return (string) $record->supplier_external;
        }

        if (filled($record->telemedicineSupplier?->name)) {
            return (string) $record->telemedicineSupplier->name;
        }

        return '—';
    }

    public static function caseCodeForOrder(OperationServiceOrder $record): string
    {
        $code = $record->operationCoordinationService?->telemedicineCase?->code;

        return filled($code) ? mb_strtoupper((string) $code) : '—';
    }

    /**
     * @param  Collection<int, OperationServiceOrder>  $orders
     * @return Collection<int, array{
     *     patient: string,
     *     document: string,
     *     orders_count: int,
     *     orders: list<array{
     *         order_number: string,
     *         case_code: string,
     *         service_type: string,
     *         status: string,
     *         status_payment: string,
     *         supplier: string,
     *         priority: string,
     *         description: string,
     *         amount_usd: string,
     *         amount_ves: string,
     *         created_at: string,
     *         approved_at: string
     *     }>
     * }>
     */
    public static function buildByPatientGroups(Collection $orders): Collection
    {
        return $orders
            ->groupBy(function (OperationServiceOrder $order): string {
                $patient = mb_strtoupper(trim(self::patientNameForOrder($order)));
                $document = mb_strtoupper(trim(self::patientDocumentForOrder($order)));

                return $patient.'|'.$document;
            })
            ->map(function (Collection $patientOrders): array {
                /** @var OperationServiceOrder $first */
                $first = $patientOrders->first();

                $rows = $patientOrders
                    ->sortByDesc(fn (OperationServiceOrder $order): string => (string) ($order->created_at?->format('Y-m-d H:i:s') ?? ''))
                    ->values()
                    ->map(fn (OperationServiceOrder $order): array => [
                        'order_number' => (string) ($order->order_number ?? '—'),
                        'case_code' => self::caseCodeForOrder($order),
                        'service_type' => filled($order->service_type) ? (string) $order->service_type : '—',
                        'status' => filled($order->status) ? (string) $order->status : '—',
                        'status_payment' => filled($order->status_payment) ? (string) $order->status_payment : '—',
                        'supplier' => self::supplierLabel($order),
                        'priority' => (string) ($order->telemedicinePriority?->name ?? '—'),
                        'description' => filled($order->description) ? (string) $order->description : '—',
                        'amount_usd' => self::formatMoney($order->total_amount_usd),
                        'amount_ves' => self::formatMoney($order->total_amount_ves),
                        'created_at' => $order->created_at?->format('d/m/Y H:i') ?? '—',
                        'approved_at' => $order->approved_at?->format('d/m/Y H:i') ?? '—',
                    ])
                    ->all();

                return [
                    'patient' => self::patientNameForOrder($first),
                    'document' => self::patientDocumentForOrder($first),
                    'orders_count' => count($rows),
                    'orders' => $rows,
                ];
            })
            ->sortBy(fn (array $group): string => mb_strtoupper($group['patient']))
            ->values();
    }

    /**
     * @param  Collection<int, OperationServiceOrder>  $orders
     * @return list<array{
     *     service_type: string,
     *     total: int,
     *     pendiente: int,
     *     en_gestion: int,
     *     finalizado: int,
     *     caducada: int,
     *     cancelado: int,
     *     otros: int,
     *     amount_usd: float,
     *     amount_ves: float
     * }>
     */
    public static function buildByServiceRows(Collection $orders): array
    {
        return $orders
            ->groupBy(fn (OperationServiceOrder $order): string => filled($order->service_type)
                ? mb_strtoupper(trim((string) $order->service_type))
                : 'SIN TIPO')
            ->map(function (Collection $serviceOrders, string $serviceType): array {
                $statusCounts = [
                    'pendiente' => 0,
                    'en_gestion' => 0,
                    'finalizado' => 0,
                    'caducada' => 0,
                    'cancelado' => 0,
                    'otros' => 0,
                ];

                foreach ($serviceOrders as $order) {
                    $status = mb_strtoupper(trim((string) ($order->status ?? '')));

                    match ($status) {
                        'PENDIENTE' => $statusCounts['pendiente']++,
                        'EN GESTION', 'EN GESTIÓN' => $statusCounts['en_gestion']++,
                        'FINALIZADO' => $statusCounts['finalizado']++,
                        'CADUCADA' => $statusCounts['caducada']++,
                        'CANCELADO', 'CANCELADA' => $statusCounts['cancelado']++,
                        default => $statusCounts['otros']++,
                    };
                }

                return [
                    'service_type' => $serviceType,
                    'total' => $serviceOrders->count(),
                    'pendiente' => $statusCounts['pendiente'],
                    'en_gestion' => $statusCounts['en_gestion'],
                    'finalizado' => $statusCounts['finalizado'],
                    'caducada' => $statusCounts['caducada'],
                    'cancelado' => $statusCounts['cancelado'],
                    'otros' => $statusCounts['otros'],
                    'amount_usd' => (float) $serviceOrders->sum(fn (OperationServiceOrder $order): float => (float) ($order->total_amount_usd ?? 0)),
                    'amount_ves' => (float) $serviceOrders->sum(fn (OperationServiceOrder $order): float => (float) ($order->total_amount_ves ?? 0)),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private static function formatMoney(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, 2, ',', '.');
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
