<?php

namespace App\Jobs;

use App\Mail\AnulatedQuotesNotificationMail;
use App\Models\IndividualQuote;
use App\Support\Concerns\ReportsScheduledExecution;
use App\Support\ScheduledTaskRunReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AnulateAgentQuotes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsScheduledExecution, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $this->runWithScheduledReport('Anulación de cotizaciones de agentes', function (): void {
            $this->anulateAgentQuotes();
        });
    }

    private function anulateAgentQuotes(): void
    {
        $quotes = IndividualQuote::query()
            ->whereNotIn('status', ['APROBADA', 'EJECUTADA'])
            ->where('created_at', '<=', now()->subDays(15))
            ->get();

        $anulatedCount = 0;
        $pdfDeleteFailures = 0;

        foreach ($quotes as $quote) {
            $quote->update(['status' => 'ANULADA']);

            if (! $this->deleteQuotePdf($quote->code)) {
                $pdfDeleteFailures++;
                ScheduledTaskRunReport::recordFailure('Error al eliminar PDF de cotización');
            }

            $anulatedCount++;
        }

        ScheduledTaskRunReport::addMetric('Cotizaciones anuladas', $anulatedCount);
        ScheduledTaskRunReport::addMetric('PDFs no eliminados', $pdfDeleteFailures);

        if ($anulatedCount > 0) {
            try {
                Mail::to('cotizaciones@tudrencasa.com')->send(
                    new AnulatedQuotesNotificationMail($anulatedCount)
                );
                ScheduledTaskRunReport::addMetric('Email resumen enviado', 'Sí');
            } catch (Throwable $exception) {
                ScheduledTaskRunReport::recordFailure('Error al enviar email de resumen');
                ScheduledTaskRunReport::addMetric('Email resumen enviado', 'No');
                Log::error('AnulateAgentQuotes: error enviando email de resumen', [
                    'message' => $exception->getMessage(),
                ]);
            }
        } else {
            ScheduledTaskRunReport::addMetric('Email resumen enviado', 'No aplica');
        }
    }

    private function deleteQuotePdf(string $code): bool
    {
        $filename = $code.'.pdf';
        $deleted = true;

        $publicPath = public_path('storage/quotes/'.$filename);
        if (file_exists($publicPath) && ! unlink($publicPath)) {
            $deleted = false;
        }

        if (Storage::disk('public')->exists('quotes/'.$filename) && ! Storage::disk('public')->delete('quotes/'.$filename)) {
            $deleted = false;
        }

        return $deleted;
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('AnulateAgentQuotes: FAILED', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
