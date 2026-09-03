<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Mail\MailLinkIndividualQuote;
use App\Support\Storefront\StorefrontQuoteShare;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class SendStorefrontQuoteShareJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 180;

    public function __construct(
        public string $code,
        public string $channel,
        public string $destination,
        public string $link,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 45, 120];
    }

    public function uniqueId(): string
    {
        return $this->code.'|'.$this->channel.'|'.$this->destination;
    }

    public function handle(): void
    {
        if ($this->channel === StorefrontQuoteShare::CHANNEL_EMAIL) {
            Mail::to($this->destination)->send(new MailLinkIndividualQuote($this->link));

            return;
        }

        $sent = NotificationController::sendLinkIndividualQuote($this->destination, $this->link);

        if ($sent !== true) {
            throw new RuntimeException('No se pudo enviar la cotización por WhatsApp.');
        }
    }
}
