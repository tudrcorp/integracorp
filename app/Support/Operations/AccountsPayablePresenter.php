<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Support\BcvOfficialRate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

final class AccountsPayablePresenter
{
    public static function quoteNumber(OperationQuoteGenerator $quote): string
    {
        return CoordinationServiceQuoteManager::formatCoordinationQuoteNumber((int) $quote->id);
    }

    public static function patientName(OperationQuoteGenerator $quote): string
    {
        if (filled($quote->telemedicinePatient?->full_name)) {
            return (string) $quote->telemedicinePatient->full_name;
        }

        if (filled($quote->operationCoordinationService?->patient)) {
            return (string) $quote->operationCoordinationService->patient;
        }

        if (filled($quote->telemedicineCase?->patient_name)) {
            return (string) $quote->telemedicineCase->patient_name;
        }

        return '—';
    }

    public static function caseCode(OperationQuoteGenerator $quote): string
    {
        $code = $quote->telemedicineCase?->code
            ?? $quote->operationCoordinationService?->telemedicineCase?->code;

        return filled($code) ? mb_strtoupper((string) $code) : '—';
    }

    public static function serviceOrderNumber(OperationQuoteGenerator $quote): ?string
    {
        $orderNumber = $quote->operationServiceOrder?->order_number;

        return filled($orderNumber) ? (string) $orderNumber : null;
    }

    public static function quoteAmountUsd(OperationQuoteGenerator $quote): ?float
    {
        $total = (float) ($quote->total ?? 0);

        if ($total > 0) {
            return round($total, 2);
        }

        $costoUsd = (float) ($quote->costo_dolares ?? 0);

        return $costoUsd > 0 ? round($costoUsd, 2) : null;
    }

    public static function quoteAmountVes(OperationQuoteGenerator $quote): ?float
    {
        $storedVes = (float) ($quote->costo_bolivares ?? 0);

        if ($storedVes > 0) {
            return round($storedVes, 2);
        }

        $amountUsd = self::quoteAmountUsd($quote);
        $bcvRate = CoordinationServiceQuoteManager::resolveBcvRateFromQuote($quote)
            ?? BcvOfficialRate::resolve();

        if ($amountUsd === null || $bcvRate === null || $bcvRate <= 0) {
            return null;
        }

        return round($amountUsd * $bcvRate, 2);
    }

    public static function bcvRateForQuote(OperationQuoteGenerator $quote): ?float
    {
        return CoordinationServiceQuoteManager::resolveBcvRateFromQuote($quote)
            ?? BcvOfficialRate::resolve();
    }

    public static function quoteSupplierLabel(OperationQuoteGenerator $quote): string
    {
        return self::supplierLabel($quote->supplier);
    }

    public static function orderSupplierLabel(OperationQuoteGenerator $quote): ?string
    {
        $order = $quote->operationServiceOrder;

        if ($order === null) {
            return null;
        }

        if (filled($order->supplier?->name)) {
            return (string) $order->supplier->name;
        }

        if (filled($order->supplier_external)) {
            return (string) $order->supplier_external;
        }

        return null;
    }

    public static function formatUsd(?float $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        return 'US$ '.number_format($amount, 2, '.', ',');
    }

    public static function formatVes(?float $amount): string
    {
        if ($amount === null) {
            return '—';
        }

        return 'Bs. '.number_format($amount, 2, '.', ',');
    }

    public static function quotePdfStoragePath(OperationQuoteGenerator $quote): ?string
    {
        if (filled($quote->quote_pdf_path)) {
            return (string) $quote->quote_pdf_path;
        }

        $order = $quote->operationServiceOrder;

        if ($order !== null && filled($order->associated_quote_pdf_path)) {
            return (string) $order->associated_quote_pdf_path;
        }

        return null;
    }

    public static function hasQuotePdf(OperationQuoteGenerator $quote): bool
    {
        return filled(self::quotePdfStoragePath($quote));
    }

    public static function quotePdfPreviewUrl(OperationQuoteGenerator $quote): ?string
    {
        $path = self::quotePdfStoragePath($quote);

        return filled($path) ? URL::to(Storage::url($path)) : null;
    }

    public static function serviceOrderForQuote(OperationQuoteGenerator $quote): ?OperationServiceOrder
    {
        return $quote->operationServiceOrder;
    }

    public static function hasServiceOrderPdf(OperationQuoteGenerator $quote): bool
    {
        return self::serviceOrderForQuote($quote) !== null;
    }

    public static function serviceOrderPdfPreviewUrl(OperationQuoteGenerator $quote): ?string
    {
        $order = self::serviceOrderForQuote($quote);

        if ($order === null) {
            return null;
        }

        return route('operations.operation-service-orders.pdf.preview', ['operationServiceOrder' => $order]);
    }

    public static function serviceOrderPdfDownloadUrl(OperationQuoteGenerator $quote): ?string
    {
        $order = self::serviceOrderForQuote($quote);

        if ($order === null) {
            return null;
        }

        return route('operations.operation-service-orders.pdf', ['operationServiceOrder' => $order]);
    }

    private static function supplierLabel(?object $supplier): string
    {
        if ($supplier === null) {
            return '—';
        }

        if (filled($supplier->name ?? null)) {
            return (string) $supplier->name;
        }

        if (filled($supplier->razon_social ?? null)) {
            return (string) $supplier->razon_social;
        }

        return '—';
    }
}
