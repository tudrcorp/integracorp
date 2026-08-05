<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CourtesyFinanceMail;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationQuoteGenerator;
use App\Support\Operations\AccountsPayablePresenter;
use App\Support\Operations\AccountsReceivableManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCourtesyFinanceEmail implements ShouldQueue
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

    public function __construct(
        public string $type,
        public int $entityId,
    ) {}

    public function handle(): void
    {
        try {
            if ($this->type === 'quote') {
                $this->sendForQuote();

                return;
            }

            if ($this->type === 'receivable') {
                $this->sendForReceivable();
            }
        } catch (Throwable $e) {
            Log::error('CORTESIA: Fallo al enviar correo a administración.', [
                'type' => $this->type,
                'entity_id' => $this->entityId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sendForQuote(): void
    {
        $quote = OperationQuoteGenerator::query()
            ->with([
                'supplier',
                'telemedicinePatient',
                'telemedicineCase',
                'operationCoordinationService',
            ])
            ->find($this->entityId);

        if (! $quote instanceof OperationQuoteGenerator || ! (bool) $quote->is_courtesy) {
            return;
        }

        $quoteNumber = AccountsPayablePresenter::quoteNumber($quote);
        $details = [
            'Documento' => 'Cuenta por pagar (cotización)',
            'Cotización' => $quoteNumber,
            'Paciente' => AccountsPayablePresenter::patientName($quote),
            'Caso' => AccountsPayablePresenter::caseCode($quote),
            'Proveedor' => AccountsPayablePresenter::quoteSupplierLabel($quote),
            'Monto US$' => AccountsPayablePresenter::formatUsd(AccountsPayablePresenter::quoteAmountUsd($quote)),
            'Monto Bs.' => AccountsPayablePresenter::formatVes(AccountsPayablePresenter::quoteAmountVes($quote)),
            'Tratamiento' => 'CORTESÍA',
        ];

        Mail::to(config('parameters.EMAIL_ADMINISTRACION'))
            ->cc(self::EMAIL_CC)
            ->send(new CourtesyFinanceMail('Cotización '.$quoteNumber, $details));
    }

    private function sendForReceivable(): void
    {
        $receivable = OperationAccountsReceivable::query()
            ->with([
                'telemedicinePatient',
                'telemedicineCase',
                'operationCoordinationService',
            ])
            ->find($this->entityId);

        if (! $receivable instanceof OperationAccountsReceivable || ! (bool) $receivable->is_courtesy) {
            return;
        }

        $number = AccountsReceivableManager::formatReceivableNumber((int) $receivable->id);
        $details = [
            'Documento' => 'Cuenta por cobrar',
            'CxC' => $number,
            'Paciente' => (string) ($receivable->telemedicinePatient?->full_name
                ?? $receivable->operationCoordinationService?->patient
                ?? '—'),
            'Caso' => filled($receivable->telemedicineCase?->code)
                ? mb_strtoupper((string) $receivable->telemedicineCase->code)
                : '—',
            'Cotización' => (string) ($receivable->quote_number ?? '—'),
            'Orden' => (string) ($receivable->service_order_number ?? '—'),
            'Monto US$' => AccountsPayablePresenter::formatUsd(
                $receivable->quote_amount_usd !== null ? (float) $receivable->quote_amount_usd : null
            ),
            'Tratamiento' => 'CORTESÍA',
        ];

        Mail::to(config('parameters.EMAIL_ADMINISTRACION'))
            ->cc(self::EMAIL_CC)
            ->send(new CourtesyFinanceMail('CxC '.$number, $details));
    }
}
