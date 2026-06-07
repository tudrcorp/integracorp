<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Support\ScheduledTaskRunReport;
use Throwable;

trait ReportsScheduledExecution
{
    /**
     * @param  callable(): void  $callback
     */
    protected function runWithScheduledReport(string $taskTitle, callable $callback): void
    {
        ScheduledTaskRunReport::begin($taskTitle);

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
