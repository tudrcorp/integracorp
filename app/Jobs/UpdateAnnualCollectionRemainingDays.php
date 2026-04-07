<?php

namespace App\Jobs;

use App\Models\AnnualCollection;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateAnnualCollectionRemainingDays implements ShouldQueue
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
        $startedAt = microtime(true);
        $today = Carbon::today();
        $processed = 0;
        $updated = 0;
        $unchanged = 0;

        try {
            AnnualCollection::query()
                ->with(['sale:id,payment_frequency'])
                ->select([
                    'id',
                    'sale_id',
                    'include_date',
                    'remaining_days',
                    'month_1',
                    'month_2',
                    'month_3',
                    'month_4',
                    'month_5',
                    'month_6',
                    'month_7',
                    'month_8',
                    'month_9',
                    'month_10',
                    'month_11',
                    'month_12',
                ])
                ->chunkById(500, function ($records) use ($today, &$processed, &$updated, &$unchanged): void {
                    foreach ($records as $record) {
                        $processed++;
                        $remainingDays = $this->calculateRemainingDays($record, $today);

                        if ((int) $record->remaining_days === $remainingDays) {
                            $unchanged++;

                            continue;
                        }

                        $record->update([
                            'remaining_days' => $remainingDays,
                        ]);

                        $updated++;
                    }
                });

            $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);

            Log::info('UpdateAnnualCollectionRemainingDays: EJECUCION OK', [
                'execution_date' => $today->toDateString(),
                'processed_records' => $processed,
                'updated_records' => $updated,
                'unchanged_records' => $unchanged,
                'elapsed_ms' => $elapsedMs,
            ]);
        } catch (Throwable $exception) {
            Log::error('UpdateAnnualCollectionRemainingDays: EJECUCION FALLIDA', [
                'execution_date' => $today->toDateString(),
                'cause' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'processed_before_failure' => $processed,
            ]);

            throw $exception;
        }
    }

    private function calculateRemainingDays(AnnualCollection $record, Carbon $today): int
    {
        $nextPaymentMonth = $this->resolveNextTrueMonth($record, $today);

        if ($nextPaymentMonth === null) {
            return 0;
        }

        $includeDate = $this->resolveIncludeDate($record->include_date, $today);
        $intervalMonths = $this->resolveFrequencyInterval($record);

        $nextPaymentDate = $this->resolveNextPaymentDateByFrequency(
            includeDate: $includeDate,
            today: $today,
            intervalMonths: $intervalMonths,
            targetMonth: $nextPaymentMonth,
        );

        $days = $today->diffInDays($nextPaymentDate->startOfDay(), false);

        return max(0, $days);
    }

    private function resolveNextTrueMonth(AnnualCollection $record, Carbon $today): ?int
    {
        $currentMonth = (int) $today->month;

        for ($offset = 0; $offset < 12; $offset++) {
            $monthNumber = (($currentMonth + $offset - 1) % 12) + 1;
            $column = 'month_'.$monthNumber;

            if (($record->{$column} ?? false) === true) {
                return $monthNumber;
            }
        }

        return null;
    }

    private function resolveIncludeDate(?string $includeDate, Carbon $today): Carbon
    {
        if (blank($includeDate)) {
            return $today->copy()->startOfDay();
        }

        $value = trim($includeDate);
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return $today->copy()->startOfDay();
        }
    }

    private function resolveFrequencyInterval(AnnualCollection $record): int
    {
        $paymentFrequency = trim((string) ($record->payment_frequency ?? $record->sale?->payment_frequency ?? ''));

        return match (strtoupper($paymentFrequency)) {
            'MENSUAL' => 1,
            'TRIMESTRAL' => 3,
            'SEMESTRAL' => 6,
            'ANUAL' => 12,
            default => 1,
        };
    }

    private function resolveNextPaymentDateByFrequency(
        Carbon $includeDate,
        Carbon $today,
        int $intervalMonths,
        int $targetMonth,
    ): Carbon {
        $candidate = $includeDate->copy();

        while ($candidate->lt($today)) {
            $candidate->addMonthsNoOverflow($intervalMonths);
        }

        for ($attempt = 0; $attempt < 60; $attempt++) {
            if ((int) $candidate->month === $targetMonth && $candidate->gte($today)) {
                return $candidate;
            }

            $candidate->addMonthsNoOverflow($intervalMonths);
        }

        $fallbackYear = (int) $today->year;
        if ($targetMonth < (int) $today->month) {
            $fallbackYear++;
        }

        if ($targetMonth === (int) $today->month && $includeDate->day < (int) $today->day) {
            $fallbackYear++;
        }

        $fallbackDay = min(
            (int) $includeDate->day,
            Carbon::create($fallbackYear, $targetMonth, 1)->daysInMonth,
        );

        return Carbon::create($fallbackYear, $targetMonth, $fallbackDay)->startOfDay();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('UpdateAnnualCollectionRemainingDays: JOB FAILED', [
            'cause' => $exception?->getMessage() ?? 'Error desconocido en la cola',
            'file' => $exception?->getFile(),
            'line' => $exception?->getLine(),
        ]);
    }
}
