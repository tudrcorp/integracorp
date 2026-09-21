<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Models\IndividualQuote;
use App\Support\Storefront\StorefrontQuotePdf;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Aviso a analistas (WhatsApp + PDF) tras crear una cotización desde la PWA.
 * Corre en cola para no bloquear la hoja de éxito del cliente.
 */
class NotifyAnalystsOfStorefrontIndividualQuoteJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public string $code,
        public string $agentLabel,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 60, 180];
    }

    public function uniqueId(): string
    {
        return $this->code;
    }

    public function handle(): void
    {
        $quote = IndividualQuote::query()
            ->where('code', $this->code)
            ->first();

        if (! $quote instanceof IndividualQuote) {
            return;
        }

        try {
            StorefrontQuotePdf::ensure($quote);
        } catch (Throwable $exception) {
            report($exception);
        }

        NotificationController::createdIndividualQuote($this->code, $this->agentLabel);
    }
}
