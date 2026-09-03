<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Mail\StorefrontPaymentMethodsMail;
use App\Support\Storefront\StorefrontPaymentMethodsDocument;
use App\Support\Storefront\StorefrontQuoteShare;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class SendStorefrontPaymentMethodsShareJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 180;

    public function __construct(
        public string $channel,
        public string $destination,
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
        return $this->channel.'|'.$this->destination;
    }

    public function handle(): void
    {
        $relative = StorefrontPaymentMethodsDocument::ensureShareableRelativePath();
        $absolute = StorefrontPaymentMethodsDocument::absolutePath();

        if ($relative === null || $absolute === null || ! is_file($absolute)) {
            throw new RuntimeException('El PDF de métodos de pago no está disponible.');
        }

        if ($this->channel === StorefrontQuoteShare::CHANNEL_EMAIL) {
            $binary = file_get_contents($absolute);

            if ($binary === false || $binary === '') {
                throw new RuntimeException('No se pudo leer el PDF de métodos de pago.');
            }

            Mail::to($this->destination)->send(new StorefrontPaymentMethodsMail(
                $binary,
                StorefrontPaymentMethodsDocument::DOWNLOAD_FILENAME,
            ));

            return;
        }

        $caption = <<<'TEXT'
*Tu Dr En Casa · Métodos de pago*

Adjuntamos el documento con los métodos de pago nacionales e internacionales.

Si necesitas ayuda para afiliarte, escríbenos.
TEXT;

        $sent = NotificationController::sendPublicStorageDocumentWhatsApp(
            $this->destination,
            $relative,
            $caption,
        );

        if ($sent !== true) {
            throw new RuntimeException('No se pudo enviar métodos de pago por WhatsApp.');
        }
    }
}
