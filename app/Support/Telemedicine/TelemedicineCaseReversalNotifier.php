<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Jobs\NotifyTelemedicineCaseReversalJob;

final class TelemedicineCaseReversalNotifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function notify(array $payload): void
    {
        NotifyTelemedicineCaseReversalJob::dispatch($payload)
            ->afterResponse()
            ->onConnection('sync');
    }
}
