<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Support\ScheduledTaskRunReport;
use Throwable;

trait ReportsScheduledExecution
{
    /**
     * @param  callable(): void  $callback
     * @param  list<string>  $readingNotes
     */
    protected function runWithScheduledReport(
        string $taskTitle,
        callable $callback,
        ?string $taskDescription = null,
        array $readingNotes = [],
    ): void {
        ScheduledTaskRunReport::begin($taskTitle, $taskDescription, $readingNotes);

        try {
            $callback();
        } catch (Throwable $exception) {
            ScheduledTaskRunReport::recordCriticalFailure($exception);

            throw $exception;
        } finally {
            ScheduledTaskRunReport::finishAndNotify();
        }
    }
}
