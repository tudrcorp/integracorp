<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MarkHelpdeskSlaBreachesJob;
use Illuminate\Console\Command;

class MarkHelpdeskSlaBreachesCommand extends Command
{
    protected $signature = 'helpdesk:mark-sla-breaches';

    protected $description = 'Marca tickets de helpdesk con SLA vencido';

    public function handle(): int
    {
        MarkHelpdeskSlaBreachesJob::dispatchSync();

        $this->info('SLA breaches actualizados.');

        return self::SUCCESS;
    }
}
