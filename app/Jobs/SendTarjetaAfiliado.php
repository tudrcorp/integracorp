<?php

namespace App\Jobs;

use App\Http\Controllers\TarjetaAfiliacionController;
use App\Mail\SendMailTarjetaAfiliado;
use App\Models\Affiliation;
use App\Support\DomPdfBatchRenderOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTarjetaAfiliado implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public Affiliation $details,
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '2048M');

        $record = $this->details;
        $record->loadMissing(['plan', 'coverage']);

        $effective = $record->effective_date ?? '';
        $hasta = '';
        if ($effective !== '') {
            try {
                $hasta = Carbon::createFromFormat('d/m/Y', $effective)->addYear()->format('d/m/Y');
            } catch (\Throwable) {
                $hasta = '';
            }
        }

        $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
            'name' => $record->full_name_ti,
            'ci' => $record->nro_identificacion_ti,
            'code' => $record->code,
            'plan' => $record->plan?->description ?? '',
            'frecuencia' => $record->payment_frequency,
            'cobertura' => $record->coverage?->price ?? '',
            'desde' => $effective,
            'hasta' => $hasta,
        ]);

        $name_pdf = $record->code.'.pdf';

        $pdf = Pdf::loadView('documents.tarjeta-afiliado', compact('data'));
        DomPdfBatchRenderOptions::apply($pdf);
        $pdf->save(public_path('storage/'.$name_pdf));

        $to = $record->email_ti ?? $record->email_payer;
        if (blank($to)) {
            Log::warning('SendTarjetaAfiliado: afiliación sin correo titular/pagador.', ['affiliation_id' => $record->id]);

            return;
        }

        Mail::to($to)->send(new SendMailTarjetaAfiliado((string) ($record->full_name_ti ?? ''), $name_pdf));
    }
}
