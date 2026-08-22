<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\AffiliateCarnetIssuedMail;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class SendAffiliateCarnetEmailJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $email,
        public string $recipientName,
        public string $affiliationCode,
        public string $carnetPath,
        public string $condicionadoPath,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (! is_file($this->carnetPath)) {
            throw new RuntimeException('No se encontró el carnet para enviar a '.$this->email);
        }

        if (! is_file($this->condicionadoPath)) {
            throw new RuntimeException('No se encontró el condicionado para enviar a '.$this->email);
        }

        Mail::to($this->email)->send(new AffiliateCarnetIssuedMail(
            recipientName: $this->recipientName,
            affiliationCode: $this->affiliationCode,
            carnetPath: $this->carnetPath,
            condicionadoPath: $this->condicionadoPath,
        ));
    }
}
