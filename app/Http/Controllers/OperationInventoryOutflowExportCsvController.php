<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OperationInventoryOutflow;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationInventoryOutflowExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'operation_inventory_outflow_export_csv_';

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

        $filename = 'salidas_inventario_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Código',
                'Producto',
                'Almacén',
                'Cantidad saliente',
                'Tipo de salida',
                'Nº caso',
                'Motivo / nota',
                'Registrado por',
                'Fecha',
            ]);

            OperationInventoryOutflow::query()
                ->with([
                    'product:id,code,name',
                    'ubication:id,name',
                    'operationInventory:id,name,barcode,ubication',
                    'telemedicineCase:id,code',
                ])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (OperationInventoryOutflow $record) use ($handle): void {
                    $caseCode = filled($record->telemedicineCase?->code)
                        ? mb_strtoupper((string) $record->telemedicineCase->code)
                        : '—';

                    fputcsv($handle, [
                        (string) ($record->product?->code ?? $record->operationInventory?->barcode ?? '—'),
                        (string) ($record->product?->name ?? $record->operationInventory?->name ?? '—'),
                        (string) ($record->ubication?->name ?? $record->operationInventory?->ubication ?? '—'),
                        (string) ((int) $record->quantity),
                        (string) ($record->type_entry ?? '—'),
                        $caseCode,
                        (string) ($record->observations ?? '—'),
                        (string) ($record->created_by ?? '—'),
                        $record->created_at?->format('d/m/Y H:i') ?? '—',
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
