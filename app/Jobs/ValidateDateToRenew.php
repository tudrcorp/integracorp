<?php

namespace App\Jobs;

use Throwable;
use App\Models\Affiliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ValidateDateToRenew implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }

    private function validateDate($details, $collect, $user)
    {
        /**
         * Validamos las fechas de las afiliaciones individuales
         */
        $dates = DB::table('affiliations')
            ->select('id', 'code', 'effective_date')
            ->get();
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("SendEmailPropuestaEconomicaMultiple: FAILED");
        Log::error($exception->getMessage());

        // Send user notification of failure, etc...

    }
}