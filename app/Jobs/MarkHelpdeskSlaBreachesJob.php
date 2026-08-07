<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\HelpDesk;
use App\Support\HelpdeskSla;
use App\Support\HelpdeskTaskStatusOptions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MarkHelpdeskSlaBreachesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        HelpDesk::query()
            ->whereNotIn('status', HelpdeskTaskStatusOptions::terminalStatuses())
            ->where(function ($query): void {
                $query->where(function ($firstResponse): void {
                    $firstResponse->whereNull('first_responded_at')
                        ->whereNotNull('first_response_due_at')
                        ->where('first_response_due_at', '<', now());
                })->orWhere(function ($resolution): void {
                    $resolution->whereNull('resolved_at')
                        ->whereNull('cancelled_at')
                        ->whereNotNull('resolution_due_at')
                        ->where('resolution_due_at', '<', now());
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($tickets): void {
                foreach ($tickets as $ticket) {
                    /** @var HelpDesk $ticket */
                    if (! HelpdeskSla::isBreached($ticket)) {
                        continue;
                    }

                    HelpdeskSla::refreshBreachFlag($ticket);
                    $ticket->save();
                }
            });
    }
}
