<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationServiceOrder;
use App\Support\Telemedicine\TelemedicinePatientDisplayName;

final class OperationServiceOrderListDisplay
{
    public const ADMINISTRATIVE_STATUS_PENDING = 'PENDIENTE';

    public const ADMINISTRATIVE_STATUS_INVOICED = 'FACTURADO';

    /**
     * Nombre visible de `administrative_status`. En la base se siguen guardando
     * PENDIENTE y FACTURADO; solo cambia lo que se muestra.
     */
    public const ADMINISTRATIVE_STATUS_LABEL = 'Documento del Aliado de Servicio';

    public const ADMINISTRATIVE_STATUS_PENDING_LABEL = 'Pendiente por Registrar';

    public const ADMINISTRATIVE_STATUS_INVOICED_LABEL = 'Registrado';

    /**
     * @return array<string, string>
     */
    public static function administrativeStatusOptions(): array
    {
        return [
            self::ADMINISTRATIVE_STATUS_PENDING => self::ADMINISTRATIVE_STATUS_PENDING_LABEL,
            self::ADMINISTRATIVE_STATUS_INVOICED => self::ADMINISTRATIVE_STATUS_INVOICED_LABEL,
        ];
    }

    /**
     * Texto visible de un estatus administrativo guardado (vacío = pendiente).
     */
    public static function administrativeStatusLabel(?string $status): string
    {
        $normalized = mb_strtoupper(trim((string) $status));

        if ($normalized === '') {
            $normalized = self::ADMINISTRATIVE_STATUS_PENDING;
        }

        return self::administrativeStatusOptions()[$normalized] ?? $normalized;
    }

    public static function serviceReferenceNumber(OperationServiceOrder $record): string
    {
        $reference = trim((string) ($record->operationCoordinationService?->reference_number ?? ''));

        return $reference !== '' ? $reference : '—';
    }

    public static function patientFullName(OperationServiceOrder $record): string
    {
        $name = TelemedicinePatientDisplayName::forCoordination($record->operationCoordinationService);

        return $name !== '' ? $name : '—';
    }

    public static function patientDocument(OperationServiceOrder $record): string
    {
        $coordination = $record->operationCoordinationService;

        if ($coordination === null) {
            return '';
        }

        if (filled($coordination->ci_patient)) {
            return trim((string) $coordination->ci_patient);
        }

        $fromPatient = trim((string) ($coordination->telemedicinePatient?->nro_identificacion ?? ''));

        return $fromPatient;
    }

    public static function patientDocumentLabel(OperationServiceOrder $record): string
    {
        $document = self::patientDocument($record);

        if ($document === '') {
            return 'Sin cédula';
        }

        return 'C.I. '.$document;
    }

    public static function specificBusinessUnit(OperationServiceOrder $record): string
    {
        $fromPatient = trim((string) ($record->operationCoordinationService?->telemedicinePatient?->specific_business_unit ?? ''));

        return $fromPatient !== '' ? $fromPatient : '—';
    }

    public static function quoteAmountUsd(OperationServiceOrder $record): ?float
    {
        $approved = $record->approvedOperationQuote;

        if ($approved !== null) {
            $approvedTotal = self::numericOrNull($approved->total ?? null);

            if ($approvedTotal !== null) {
                return $approvedTotal;
            }

            $approvedCost = self::numericOrNull($approved->costo_dolares ?? null);

            if ($approvedCost !== null) {
                return $approvedCost;
            }
        }

        return self::supplierQuotesAmountUsd($record);
    }

    /**
     * Costo del servicio sin la ganancia de TDG: lo que debería facturar el aliado.
     *
     * Con cotización aprobada es su costo base (`costo_dolares`, o `subtotal` si
     * falta); el `total` incluye la ganancia y es el precio de venta. Sin cotización
     * aprobada se usan las cotizaciones del proveedor, que ya vienen sin ganancia.
     */
    public static function serviceCostUsd(OperationServiceOrder $record): ?float
    {
        $approved = $record->approvedOperationQuote;

        if ($approved !== null) {
            foreach (['costo_dolares', 'subtotal'] as $column) {
                $cost = self::numericOrNull($approved->{$column} ?? null);

                if ($cost !== null && $cost > 0) {
                    return round($cost, 2);
                }
            }
        }

        return self::supplierQuotesAmountUsd($record);
    }

    public static function serviceCostLabel(OperationServiceOrder $record): string
    {
        $amount = self::serviceCostUsd($record);

        return $amount === null ? '—' : 'US$ '.number_format($amount, 2, ',', '.');
    }

    /**
     * Suma de las cotizaciones del proveedor cargadas en la orden (medicamentos).
     */
    private static function supplierQuotesAmountUsd(OperationServiceOrder $record): ?float
    {
        $aggregated = self::numericOrNull($record->getAttribute('operation_service_order_quotes_sum_total_amount_usd'));

        if ($aggregated !== null) {
            return $aggregated;
        }

        if ($record->relationLoaded('operationServiceOrderQuotes')) {
            $sum = 0.0;
            $hasQuoteAmount = false;

            foreach ($record->operationServiceOrderQuotes as $quote) {
                $amount = self::numericOrNull($quote->total_amount_usd ?? null);

                if ($amount === null) {
                    continue;
                }

                $hasQuoteAmount = true;
                $sum += $amount;
            }

            if ($hasQuoteAmount) {
                return round($sum, 2);
            }
        }

        // Sin cotización propia no hay monto: el `quote_price` de la coordinación es el de
        // la última cotización registrada, que puede no ser la de esta orden (p. ej. una
        // orden anulada mostraba el precio de la cotización de su reemplazo).
        return null;
    }

    public static function quoteAmountLabel(OperationServiceOrder $record): string
    {
        $amount = self::quoteAmountUsd($record);

        if ($amount === null) {
            return '—';
        }

        return 'US$ '.number_format($amount, 2, ',', '.');
    }

    public static function quoteCodeLabel(mixed $quoteId): string
    {
        if (! filled($quoteId) || ! is_numeric($quoteId)) {
            return '—';
        }

        return 'COT-'.str_pad((string) ((int) $quoteId), 6, '0', STR_PAD_LEFT);
    }

    public static function quotePdfStoragePath(OperationServiceOrder $record): ?string
    {
        $quote = $record->approvedOperationQuote;

        if ($quote !== null && filled($quote->quote_pdf_path)) {
            return (string) $quote->quote_pdf_path;
        }

        if (filled($record->associated_quote_pdf_path)) {
            return (string) $record->associated_quote_pdf_path;
        }

        return null;
    }

    public static function administrativeStatus(OperationServiceOrder $record): string
    {
        $status = mb_strtoupper(trim((string) ($record->administrative_status ?? '')));

        return $status !== '' ? $status : self::ADMINISTRATIVE_STATUS_PENDING;
    }

    public static function administrativeStatusColor(?string $status): string
    {
        $normalized = mb_strtoupper(trim((string) $status));

        return match ($normalized) {
            self::ADMINISTRATIVE_STATUS_INVOICED => 'success',
            self::ADMINISTRATIVE_STATUS_PENDING, '' => 'danger',
            default => 'gray',
        };
    }

    public static function administrativeStatusIcon(?string $status): string
    {
        $normalized = mb_strtoupper(trim((string) $status));

        return match ($normalized) {
            self::ADMINISTRATIVE_STATUS_INVOICED => 'heroicon-m-check-badge',
            default => 'heroicon-m-clipboard-document-check',
        };
    }

    public static function hasInvoice(OperationServiceOrder $record): bool
    {
        return filled($record->invoice_number) || filled($record->invoice_receipt_number) || filled($record->invoice_file_path);
    }

    public static function invoiceAmountUsd(OperationServiceOrder $record): ?float
    {
        return self::numericOrNull($record->invoice_amount_usd);
    }

    private static function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
