<?php

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Mail\ReSendDocument;
use App\Support\Affiliations\Concerns\LogsAffiliationJobFailures;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ResendMailNotificacionAfiliacionIndividual implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, LogsAffiliationJobFailures, Queueable, SerializesModels;

    protected $email;

    protected $phone;

    protected $title;

    protected $name_ti;

    protected $name_pdf;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct($email, $phone, $title, $name_ti, $name_pdf)
    {
        $this->email = $email;
        $this->phone = $phone;
        $this->title = $title;
        $this->name_ti = $name_ti;
        $this->name_pdf = $name_pdf;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->runWithAffiliationFailureLogging(function (): void {
            if ($this->email != null) {
                Mail::to($this->email)->send(new ReSendDocument($this->title, $this->name_ti, $this->name_pdf));
            }

            if ($this->phone != null) {
                $link = config('app.url').'/storage/'.$this->name_pdf;

                $body = <<<'HTML'

                Hola, buenas tardes. 👋
                Espero se encuentre bien. 
                Le comento que el documento que recibió es la cotización correspondiente al Plan Especial , con todas las coberturas y tarifas detalladas. 
                Si tiene alguna duda o necesita más información, no dude en comunicarse con nosotros. 😊   

                Equipo Integracorp-TDC 
                📱 WhatsApp: (+58) 424 222 00 56
                ✉️ Email: comercial@tudrencasa.com 

            HTML;

                NotificationController::sendCotizaPlanInicial($this->phone, $body, $link, $this->name_pdf);
            }
        }, $this->affiliationJobFailureContext());
    }

    public function failed(?Throwable $exception): void
    {
        $this->logAffiliationJobFailure($exception, $this->affiliationJobFailureContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function affiliationJobFailureContext(): array
    {
        $pdfPath = public_path('storage/'.$this->name_pdf);

        return [
            'action' => 'forward',
            'email' => $this->email,
            'phone' => $this->phone,
            'title' => $this->title,
            'name_ti' => $this->name_ti,
            'name_pdf' => $this->name_pdf,
            'pdf_path' => $pdfPath,
            'pdf_exists' => is_file($pdfPath),
        ];
    }
}
