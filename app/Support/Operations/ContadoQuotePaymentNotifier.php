<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Jobs\SendContadoQuotePaymentEmail;
use App\Jobs\SendNotificacionWhatsApp;
use App\Models\OperationQuoteGenerator;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class ContadoQuotePaymentNotifier
{
    /**
     * Teléfonos destino para la notificación de pago de contado por WhatsApp.
     *
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

        if (! $quote instanceof OperationQuoteGenerator) {
            Log::warning('CONTADO: cotización no encontrada para notificar.', ['quote_id' => $quoteId]);

            return;
        }

        $userId = Auth::id();

        SendContadoQuotePaymentEmail::dispatch($quoteId);

        $caption = self::buildWhatsAppCaption($quote);

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
