<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Enums\SystemNotificationKey;
use App\Jobs\SendContadoQuotePaymentEmail;
use App\Jobs\SendNotificacionWhatsApp;
use App\Models\OperationQuoteGenerator;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\InternalNotifications\InternalMessaging;
use App\Support\SystemNotificationRecipients;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class ContadoQuotePaymentNotifier
{
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

        if (! $quote instanceof OperationQuoteGenerator) {
            Log::warning('CONTADO: cotización no encontrada para notificar.', ['quote_id' => $quoteId]);

            return;
        }

        $userId = Auth::id();

        SendContadoQuotePaymentEmail::dispatch($quoteId);

        $caption = self::buildWhatsAppCaption($quote);

        /**
         * Los teléfonos salen de «Pagos de contado urgentes» del Centro de notificaciones (antes, dos
         * números fijos aquí). Telegram para los vinculados y los grupos; el resto, por WhatsApp.
         */
        $key = SystemNotificationKey::ContadoQuotePayment;
        $phones = SystemNotificationRecipients::isActive($key) ? SystemNotificationRecipients::phones($key) : [];
        $route = InternalMessaging::route($key, 'Pago de contado urgente', $caption, $phones);

        foreach ($route['whatsapp'] as $phone) {
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
                    'context' => 'contado_quote_payment',
                    'quote_id' => $quoteId,
                ],
            );
        }
    }

    public static function buildWhatsAppCaption(OperationQuoteGenerator $quote): string
    {
        $quoteNumber = AccountsPayablePresenter::quoteNumber($quote);
        $patient = AccountsPayablePresenter::patientName($quote);
        $caseCode = AccountsPayablePresenter::caseCode($quote);
        $supplier = AccountsPayablePresenter::quoteSupplierLabel($quote);
        $amountUsd = AccountsPayablePresenter::formatUsd(AccountsPayablePresenter::quoteAmountUsd($quote));
        $amountVes = AccountsPayablePresenter::formatVes(AccountsPayablePresenter::quoteAmountVes($quote));
        $bcvRate = AccountsPayablePresenter::bcvRateForQuote($quote);
        $bcvLabel = $bcvRate !== null ? number_format($bcvRate, 2, '.', ',') : '—';
        $date = now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i');

        return <<<TEXT
        🚨 *PAGO DE CONTADO* 🚨
        Esta cuenta por pagar debe cancelarse de inmediato.

        *Cotización:* {$quoteNumber}
        *Paciente:* {$patient}
        *Caso:* {$caseCode}
        *Proveedor:* {$supplier}
        *Monto US\$:* {$amountUsd}
        *Monto Bs.:* {$amountVes}
        *Tasa BCV:* {$bcvLabel}
        *Fecha:* {$date}

        Por favor procesar el pago a la brevedad.
        TEXT;
    }
}
