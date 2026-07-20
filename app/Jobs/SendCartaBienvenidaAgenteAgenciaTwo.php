<?php

namespace App\Jobs;

use App\Mail\MailCartaBienvenidaAgenteAgenciaTwo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCartaBienvenidaAgenteAgenciaTwo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $code;

    public $name;

    public $email;

    public $password;

    /**
     * Create a new job instance.
     */
    public function __construct($code, $name, $email, $password)
    {
        $this->code = $code;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', '2048M');

        $code = $this->code;
        $name = $this->name;
        $email = $this->email;
        $password = $this->password;
        $name_pdf = $code.'.pdf';

        try {
            $storagePath = public_path('storage');
            if (! File::exists($storagePath)) {
                File::ensureDirectoryExists($storagePath);
            }

            $pdf = Pdf::loadView('documents.carta-bienvenida-agencia', compact('code', 'name'));
            $pdf->save($storagePath.'/'.$name_pdf);
            unset($pdf);

            Mail::to($email)
                ->bcc('solrodriguez@tudrencasa.com')
                ->send(new MailCartaBienvenidaAgenteAgenciaTwo($code, $name, $name_pdf, $email, $password));

            Log::info('MASTER-AGENCIES: Carta de bienvenida enviada con éxito.', [
                'code' => $code,
                'email' => $email,
            ]);
        } catch (\Throwable $th) {
            Log::error('MASTER-AGENCIES: Error enviando carta de bienvenida.', [
                'code' => $code,
                'email' => $email,
                'error' => $th->getMessage(),
                'exception' => $th::class,
            ]);

            throw $th;
        }
    }
}
