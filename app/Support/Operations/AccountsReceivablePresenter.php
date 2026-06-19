<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationAccountsReceivable;

final class AccountsReceivablePresenter
{
    public static function receivableNumber(OperationAccountsReceivable $record): string
    {
        return AccountsReceivableManager::formatReceivableNumber((int) $record->id);
    }

    public static function patientName(OperationAccountsReceivable $record): string
    {
        if (filled($record->telemedicinePatient?->full_name)) {
            return (string) $record->telemedicinePatient->full_name;
        }

        if (filled($record->operationCoordinationService?->patient)) {
            return (string) $record->operationCoordinationService->patient;
        }

        if (filled($record->telemedicineCase?->patient_name)) {
            return (string) $record->telemedicineCase->patient_name;
        }

        return '—';
    }

    public static function caseCode(OperationAccountsReceivable $record): string
    {
        $code = $record->telemedicineCase?->code
            ?? $record->operationCoordinationService?->telemedicineCase?->code;

        return filled($code) ? mb_strtoupper((string) $code) : '—';
    }

    public static function quoteNumber(OperationAccountsReceivable $record): ?string
    {
        return filled($record->quote_number) ? (string) $record->quote_number : null;
    }

    public static function serviceOrderNumber(OperationAccountsReceivable $record): ?string
    {
        return filled($record->service_order_number) ? (string) $record->service_order_number : null;
    }

    public static function quoteAmountUsd(OperationAccountsReceivable $record): ?float
    {
        $amount = $record->quote_amount_usd;

        return $amount !== null ? round((float) $amount, 2) : null;
    }

    public static function quoteAmountVes(OperationAccountsReceivable $record): ?float
    {
        $amount = $record->quote_amount_ves;

        return $amount !== null ? round((float) $amount, 2) : null;
    }

    public static function bcvRate(OperationAccountsReceivable $record): ?float
    {
        $rate = $record->bcv_rate;

        return $rate !== null ? round((float) $rate, 4) : null;
    }

    public static function reassignmentSupplierName(OperationAccountsReceivable $record): string
    {
        if (filled($record->reassignment_supplier_name)) {
            return (string) $record->reassignment_supplier_name;
        }

        if (filled($record->reassignmentSupplier?->name)) {
            return (string) $record->reassignmentSupplier->name;
        }

        return '—';
    }

    public static function reassignedAnalystName(OperationAccountsReceivable $record): string
    {
        if (filled($record->reassigned_by_analyst_name)) {
            return (string) $record->reassigned_by_analyst_name;
        }

        if (filled($record->reassignedByUser?->name)) {
            return (string) $record->reassignedByUser->name;
        }

        return '—';
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

    public static function statusLabel(?string $status): string
    {
        return match (mb_strtoupper(trim((string) $status))) {
            OperationAccountsReceivable::STATUS_PENDING_TDG => 'Pendiente gestión TDG',
            OperationAccountsReceivable::STATUS_QUOTE_ASSIGNED => 'Cotización asignada',
            OperationAccountsReceivable::STATUS_COMPLETED => 'Gestión completada',
            default => filled($status) ? (string) $status : '—',
        };
    }

    public static function statusColor(?string $status): string
    {
        return match (mb_strtoupper(trim((string) $status))) {
            OperationAccountsReceivable::STATUS_PENDING_TDG => 'warning',
            OperationAccountsReceivable::STATUS_QUOTE_ASSIGNED => 'info',
            OperationAccountsReceivable::STATUS_COMPLETED => 'success',
            default => 'gray',
        };
    }
}
