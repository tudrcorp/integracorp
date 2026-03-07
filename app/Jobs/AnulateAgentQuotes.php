<?php

namespace App\Jobs;

use App\Mail\AnulatedQuotesNotificationMail;
use App\Models\IndividualQuote;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $this->anulateAgentQuotes();
    }

    private function anulateAgentQuotes(): void
    {
        $quotes = IndividualQuote::query()
            ->whereNotIn('status', ['APROBADA', 'EJECUTADA'])
            ->get();

        $anulatedCount = 0;

        foreach ($quotes as $quote) {
            $quote->update(['status' => 'ANULADA']);
            $this->deleteQuotePdf($quote->code);
            $anulatedCount++;
        }

        if ($anulatedCount > 0) {
            Mail::to('cotizaciones@tudrencasa.com')->send(
                new AnulatedQuotesNotificationMail($anulatedCount)
            );
        }
    }

    private function deleteQuotePdf(string $code): void
    {
        $filename = $code.'.pdf';

        $publicPath = public_path('storage/quotes/'.$filename);
        if (file_exists($publicPath)) {
            unlink($publicPath);
        }

        if (Storage::disk('public')->exists('quotes/'.$filename)) {
            Storage::disk('public')->delete('quotes/'.$filename);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('AnulateAgentQuotes: FAILED', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
