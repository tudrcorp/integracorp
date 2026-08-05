<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Jobs\SendCourtesyFinanceEmail;
use App\Jobs\SendNotificacionWhatsApp;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class CourtesyFinanceNotifier
{
    /**
     * @var array<int, string>
     */
    private const WHATSAPP_PHONES = [
        '04242875732',
        '04143027250',
    ];

    public static function dispatchForQuote(int $quoteId): void
    {
        $quote = OperationQuoteGenerator::query()
            ->with([
                'supplier',
                'telemedicinePatient',
                'telemedicineCase',
                'operationCoordinationService',
                'operationServiceOrder',
            ])
            ->find($quoteId);

        if (! $quote instanceof OperationQuoteGenerator || ! (bool) $quote->is_courtesy) {
            return;
        }

        self::dispatchNotifications(
            context: 'courtesy_quote',
            entityId: $quoteId,
            caption: self::buildQuoteWhatsAppCaption($quote),
            emailPayload: [
                'type' => 'quote',
                'id' => $quoteId,
            ],
        );
    }

    public static function dispatchForReceivable(int $receivableId): void
    {
        $receivable = OperationAccountsReceivable::query()
            ->with([
                'operationCoordinationService',
                'operationQuoteGenerator.supplier',
                'operationServiceOrder',
                'telemedicineCase',
                'telemedicinePatient',
            ])
            ->find($receivableId);

        if (! $receivable instanceof OperationAccountsReceivable || ! (bool) $receivable->is_courtesy) {
            return;
        }

        self::dispatchNotifications(
            context: 'courtesy_receivable',
            entityId: $receivableId,
            caption: self::buildReceivableWhatsAppCaption($receivable),
            emailPayload: [
                'type' => 'receivable',
                'id' => $receivableId,
            ],
        );
    }

    public static function dispatchForServiceOrder(int $orderId): void
    {
        $order = OperationServiceOrder::query()
            ->with(['supplier', 'operationCoordinationService.telemedicineCase', 'operationCoordinationService.telemedicinePatient'])
            ->find($orderId);

        if (! $order instanceof OperationServiceOrder || ! (bool) $order->is_courtesy) {
            return;
        }

        // La CxC se notifica en sync; aquí solo si la orden cortesía existe como referencia CxP vía cotización.
        // No duplicar: la notificación de OS pura se cubre cuando hay quote/CxC.
    }

    /**
     * @param  array{type: string, id: int}  $emailPayload
     */
    private static function dispatchNotifications(
        string $context,
        int $entityId,
        string $caption,
        array $emailPayload,
    ): void {
        $userId = Auth::id();

        SendCourtesyFinanceEmail::dispatch($emailPayload['type'], $emailPayload['id']);

        foreach (self::WHATSAPP_PHONES as $phone) {
            $normalized = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone);

            if ($normalized === null) {
                continue;
            }

            SendNotificacionWhatsApp::dispatch(
                $userId,
                $caption,
                $normalized,
                null,
                [
                    'panel' => 'operations',
                    'context' => $context,
                    'entity_id' => $entityId,
                ],
            );
        }

        Log::info('CORTESIA: notificación enviada a administración.', [
            'context' => $context,
            'entity_id' => $entityId,
        ]);
    }

    public static function buildQuoteWhatsAppCaption(OperationQuoteGenerator $quote): string
    {
        $quoteNumber = AccountsPayablePresenter::quoteNumber($quote);
        $patient = AccountsPayablePresenter::patientName($quote);
        $caseCode = AccountsPayablePresenter::caseCode($quote);
        $supplier = AccountsPayablePresenter::quoteSupplierLabel($quote);
        $amountUsd = AccountsPayablePresenter::formatUsd(AccountsPayablePresenter::quoteAmountUsd($quote));
        $amountVes = AccountsPayablePresenter::formatVes(AccountsPayablePresenter::quoteAmountVes($quote));
        $date = now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i');

        return <<<TEXT
        🎁 *SERVICIO POR CORTESÍA* 🎁
        Se generó una cuenta por pagar (cotización) marcada como CORTESÍA.

        *Cotización:* {$quoteNumber}
        *Paciente:* {$patient}
        *Caso:* {$caseCode}
        *Proveedor:* {$supplier}
        *Monto US\$:* {$amountUsd}
        *Monto Bs.:* {$amountVes}
        *Fecha:* {$date}

        Favor gestionar con el tratamiento de cortesía.
        TEXT;
    }

    public static function buildReceivableWhatsAppCaption(OperationAccountsReceivable $receivable): string
    {
        $number = AccountsReceivableManager::formatReceivableNumber((int) $receivable->id);
        $patient = (string) ($receivable->telemedicinePatient?->full_name
            ?? $receivable->operationCoordinationService?->patient
            ?? '—');
        $caseCode = filled($receivable->telemedicineCase?->code)
            ? mb_strtoupper((string) $receivable->telemedicineCase->code)
            : '—';
        $quoteNumber = (string) ($receivable->quote_number ?? '—');
        $orderNumber = (string) ($receivable->service_order_number ?? '—');
        $amountUsd = AccountsPayablePresenter::formatUsd(
            $receivable->quote_amount_usd !== null ? (float) $receivable->quote_amount_usd : null
        );
        $date = now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i');

        return <<<TEXT
        🎁 *SERVICIO POR CORTESÍA* 🎁
        Se generó/actualizó una cuenta por cobrar marcada como CORTESÍA.

        *CxC:* {$number}
        *Paciente:* {$patient}
        *Caso:* {$caseCode}
        *Cotización:* {$quoteNumber}
        *Orden:* {$orderNumber}
        *Monto US\$:* {$amountUsd}
        *Fecha:* {$date}

        Favor gestionar con el tratamiento de cortesía.
        TEXT;
    }
}
