<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\BcvOfficialRate;
use Illuminate\Support\Facades\Auth;

final class AccountsReceivableManager
{
    public static function formatReceivableNumber(int $receivableId): string
    {
        return 'CXC-'.str_pad((string) $receivableId, 6, '0', STR_PAD_LEFT);
    }

    public static function createFromTdgReassignment(
        OperationCoordinationService $coordination,
        string $reassignmentReason,
        ?User $user = null,
    ): OperationAccountsReceivable {
        $user ??= Auth::user();
        $supplierId = self::resolveReassignmentSupplierId($coordination, $user);

        return OperationAccountsReceivable::query()->create([
            'operation_coordination_service_id' => $coordination->id,
            'telemedicine_patient_id' => $coordination->telemedicine_patient_id,
            'telemedicine_case_id' => $coordination->telemedicine_case_id,
            'operation_quote_generator_id' => null,
            'operation_service_order_id' => null,
            'quote_number' => null,
            'service_order_number' => null,
            'quote_amount_usd' => null,
            'quote_amount_ves' => null,
            'bcv_rate' => null,
            'reassignment_reason' => trim($reassignmentReason),
            'reassignment_supplier_id' => $supplierId,
            'reassignment_supplier_name' => self::resolveSupplierName($supplierId),
            'reassigned_by_user_id' => $user?->id,
            'reassigned_by_analyst_name' => filled($user?->name) ? (string) $user->name : 'SISTEMA',
            'status' => OperationAccountsReceivable::STATUS_PENDING_TDG,
            'created_by' => filled($user?->name) ? (string) $user->name : 'SISTEMA',
            'updated_by' => filled($user?->name) ? (string) $user->name : 'SISTEMA',
        ]);
    }

    public static function syncFromQuote(OperationQuoteGenerator $quote): void
    {
        $coordinationId = $quote->operation_coordination_service_id;

        if ($coordinationId === null) {
            return;
        }

        $receivable = self::pendingReceivableForCoordination((int) $coordinationId);

        if ($receivable === null) {
            return;
        }

        $amountUsd = AccountsPayablePresenter::quoteAmountUsd($quote);
        $amountVes = AccountsPayablePresenter::quoteAmountVes($quote);
        $bcvRate = AccountsPayablePresenter::bcvRateForQuote($quote) ?? BcvOfficialRate::resolve();

        $receivable->update([
            'operation_quote_generator_id' => $quote->id,
            'quote_number' => CoordinationServiceQuoteManager::formatCoordinationQuoteNumber((int) $quote->id),
            'quote_amount_usd' => $amountUsd,
            'quote_amount_ves' => $amountVes,
            'bcv_rate' => $bcvRate,
            'status' => filled($receivable->operation_service_order_id)
                ? OperationAccountsReceivable::STATUS_COMPLETED
                : OperationAccountsReceivable::STATUS_QUOTE_ASSIGNED,
            'updated_by' => Auth::user()?->name ?? 'SISTEMA',
        ]);
    }

    public static function syncFromServiceOrder(OperationServiceOrder $order): void
    {
        $coordinationId = $order->operation_coordination_service_id;

        if ($coordinationId === null) {
            return;
        }

        $receivable = self::pendingReceivableForCoordination((int) $coordinationId);

        if ($receivable === null) {
            return;
        }

        $updates = [
            'operation_service_order_id' => $order->id,
            'service_order_number' => filled($order->order_number) ? (string) $order->order_number : null,
            'updated_by' => Auth::user()?->name ?? 'SISTEMA',
        ];

        if ($receivable->operation_quote_generator_id !== null) {
            $updates['status'] = OperationAccountsReceivable::STATUS_COMPLETED;
        }

        $receivable->update($updates);
    }

    private static function pendingReceivableForCoordination(int $coordinationId): ?OperationAccountsReceivable
    {
        return OperationAccountsReceivable::query()
            ->where('operation_coordination_service_id', $coordinationId)
            ->whereIn('status', [
                OperationAccountsReceivable::STATUS_PENDING_TDG,
                OperationAccountsReceivable::STATUS_QUOTE_ASSIGNED,
            ])
            ->latest('id')
            ->first();
    }

    private static function resolveReassignmentSupplierId(
        OperationCoordinationService $coordination,
        ?User $user,
    ): ?int {
        if (filled($coordination->supplier_id)) {
            return (int) $coordination->supplier_id;
        }

        if (filled($user?->supplier_id)) {
            return (int) $user->supplier_id;
        }

        return null;
    }

    private static function resolveSupplierName(?int $supplierId): ?string
    {
        if ($supplierId === null) {
            return null;
        }

        $supplier = Supplier::query()->find($supplierId);

        if ($supplier === null) {
            return null;
        }

        if (filled($supplier->name)) {
            return (string) $supplier->name;
        }

        if (filled($supplier->razon_social)) {
            return (string) $supplier->razon_social;
        }

        return null;
    }
}
