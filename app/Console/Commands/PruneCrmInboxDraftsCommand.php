<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\CrmInbox\CrmInboxWatch;
use Illuminate\Console\Command;

/**
 * Borra los PDF de propuestas del CRM que se calcularon y nunca se enviaron.
 */
class PruneCrmInboxDraftsCommand extends Command
{
    protected $signature = 'crm-inbox:prune-drafts';

    protected $description = 'Borra las propuestas calculadas en Atención WhatsApp que nunca se enviaron';

    public function handle(): int
    {
        $this->info('Propuestas borradas: '.CrmInboxWatch::pruneDrafts().'.');

        return self::SUCCESS;
    }
}
