<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderTableReportPdfService;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationServiceOrderExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'operation_service_order_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    public function __invoke(Request $request): StreamedResponse
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(400, 'Token de exportación no válido o expirado.');
        }

        $ids = Cache::pull(self::CACHE_PREFIX.$token);

        if (! is_array($ids) || $ids === []) {
            abort(400, 'Token de exportación no válido o expirado.');
        }

        $ids = array_values(array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0,
        ));

        $filename = 'ordenes_servicio_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Nº orden',
                'Paciente',
                'Documento',
                'Nº caso',
                'Tipo de servicio',
                'Estado',
                'Estado de pago',
                'Proveedor',
                'Prioridad',
                'Descripción',
                'Moneda',
                'Monto USD',
                'Monto VES',
                'Método de pago',
                'Gestionado por',
                'Creado por',
                'Creado',
                'Aprobado',
            ]);

            OperationServiceOrder::query()
                ->with([
                    'supplier:id,name',
                    'telemedicineSupplier:id,name',
                    'telemedicinePriority:id,name',
                    'operationCoordinationService:id,patient,telemedicine_patient_id,telemedicine_case_id,ci_patient',
                    'operationCoordinationService.telemedicinePatient:id,full_name,nro_identificacion',
                    'operationCoordinationService.telemedicineCase:id,code,patient_name',
                ])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (OperationServiceOrder $record) use ($handle): void {
                    fputcsv($handle, [
                        (string) ($record->order_number ?? '—'),
                        OperationServiceOrderTableReportPdfService::patientNameForOrder($record),
                        OperationServiceOrderTableReportPdfService::patientDocumentForOrder($record),
                        OperationServiceOrderTableReportPdfService::caseCodeForOrder($record),
                        (string) ($record->service_type ?? '—'),
                        (string) ($record->status ?? '—'),
                        (string) ($record->status_payment ?? '—'),
                        OperationServiceOrderTableReportPdfService::supplierLabel($record),
                        (string) ($record->telemedicinePriority?->name ?? '—'),
                        (string) ($record->description ?? '—'),
                        (string) ($record->currency ?? '—'),
                        $record->total_amount_usd !== null ? (string) $record->total_amount_usd : '—',
                        $record->total_amount_ves !== null ? (string) $record->total_amount_ves : '—',
                        (string) ($record->payment_method ?? '—'),
                        (string) ($record->managed_by ?? '—'),
                        (string) ($record->created_by ?? '—'),
                        $record->created_at?->format('d/m/Y H:i') ?? '—',
                        $record->approved_at?->format('d/m/Y H:i') ?? '—',
                    ]);
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

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
        Cache::put(self::CACHE_PREFIX.$token, $ids, self::TOKEN_TTL_SECONDS);

        return $token;
    }
}
