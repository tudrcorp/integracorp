<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationAccountsPayableExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'operation_accounts_payable_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'Fecha factura',
            'Fecha de registro',
            'Proveedor',
            'RIF',
            'Unidad de negocio',
            'Orden de servicio',
            'N.º factura',
            'N.º control',
            'Monto factura',
            'Moneda',
            'Estatus de pago',
            'Referencia de pago',
            'Fecha del pago',
            'Banco nacional',
            'Banco internacional',
            'Pago US$',
            'Pago Bs.',
            'Comprobante de pago',
            'Documento',
            'Registrado por',
        ];
    }

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

        $filename = 'cuentas_por_pagar_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::headers());

            OperationAccountsPayable::query()
                ->with([
                    'businessUnit:id,definition,code',
                    'operationServiceOrder:id,order_number',
                ])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (OperationAccountsPayable $record) use ($handle): void {
                    fputcsv($handle, self::buildRow($record));
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return list<string>
     */
    public static function buildRow(OperationAccountsPayable $record): array
    {
        return [
            $record->invoice_date?->format('d/m/Y') ?? '—',
            $record->invoice_registration_date?->format('d/m/Y') ?? '—',
            $record->supplierLabel(),
            filled($record->supplier_rif) ? (string) $record->supplier_rif : '—',
            filled($record->businessUnit?->definition) ? (string) $record->businessUnit->definition : '—',
            $record->operationServiceOrder?->order_number ?: 'Carga manual',
            filled($record->invoice_number) ? (string) $record->invoice_number : '—',
            filled($record->invoice_control_number) ? (string) $record->invoice_control_number : '—',
            self::money($record->invoice_amount, $record->invoice_currency),
            filled($record->invoice_currency) ? (string) $record->invoice_currency : '—',
            StatusCuentaPorPagar::labelFromMixed($record->payment_status),
            filled($record->payment_reference) ? (string) $record->payment_reference : '—',
            $record->payment_date?->format('d/m/Y') ?? '—',
            filled($record->national_bank) ? (string) $record->national_bank : '—',
            filled($record->international_bank) ? (string) $record->international_bank : '—',
            $record->payment_amount_usd !== null ? self::money($record->payment_amount_usd, 'USD') : '—',
            $record->payment_amount_ves !== null ? self::money($record->payment_amount_ves, 'VES') : '—',
            $record->hasPaymentReceipt() ? 'Adjunto' : 'Sin adjuntar',
            $record->hasInvoiceDocument() ? 'Adjunto' : 'Sin adjuntar',
            filled($record->created_by) ? (string) $record->created_by : '—',
        ];
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

    private static function money(mixed $amount, ?string $currency): string
    {
        $symbol = $currency === 'VES' ? 'Bs. ' : 'US$ ';

        return $symbol.number_format((float) $amount, 2, ',', '.');
    }
}
