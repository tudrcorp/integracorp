<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ContadoQuotePaymentMail;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Support\Operations\AccountsPayablePresenter;
use App\Support\Operations\CoordinationServiceQuoteManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendContadoQuotePaymentEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    private const EMAIL_CC = 'solrodriguez@tudrencasa.com';

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function __construct(public int $quoteId) {}

    public function handle(): void
    {
        $quote = OperationQuoteGenerator::query()
            ->with([
                'supplier',
                'telemedicinePatient',
                'telemedicineCase',
                'operationCoordinationService',
                'operationServiceOrder',
            ])
            ->find($this->quoteId);

        if (! $quote instanceof OperationQuoteGenerator) {
            Log::warning('CONTADO: cotización no encontrada para enviar correo.', ['quote_id' => $this->quoteId]);

            return;
        }

        $relativePath = AccountsPayablePresenter::quotePdfStoragePath($quote);

        if (! filled($relativePath)) {
            $coordination = $quote->operationCoordinationService;

            if ($coordination instanceof OperationCoordinationService) {
                $relativePath = CoordinationServiceQuoteManager::resolveQuotePdfPathForOrder($quote, $coordination);
            }
        }

        $quoteNumber = AccountsPayablePresenter::quoteNumber($quote);
        $bcvRate = AccountsPayablePresenter::bcvRateForQuote($quote);

        $details = [
            'Cotización' => $quoteNumber,
            'Paciente' => AccountsPayablePresenter::patientName($quote),
            'Caso' => AccountsPayablePresenter::caseCode($quote),
            'Proveedor' => AccountsPayablePresenter::quoteSupplierLabel($quote),
            'Monto US$' => AccountsPayablePresenter::formatUsd(AccountsPayablePresenter::quoteAmountUsd($quote)),
            'Monto Bs.' => AccountsPayablePresenter::formatVes(AccountsPayablePresenter::quoteAmountVes($quote)),
            'Tasa BCV' => $bcvRate !== null ? number_format($bcvRate, 2, '.', ',') : '—',
            'Condición de pago' => 'CONTADO (cancelar de inmediato)',
        ];

        try {
            Mail::to(config('parameters.EMAIL_ADMINISTRACION'))
                ->cc(self::EMAIL_CC)
                ->send(new ContadoQuotePaymentMail($quoteNumber, $details, $relativePath));
        } catch (Throwable $e) {
            Log::error('CONTADO: Fallo al enviar correo de pago de contado.', [
                'quote_id' => $this->quoteId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
