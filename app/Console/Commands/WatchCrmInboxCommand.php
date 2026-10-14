<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\CrmInbox\CrmInboxWatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Vigilancia de Atención WhatsApp. Corre cada minuto en el scheduler, fuera de la cola: si los
 * workers se caen, igual reintenta envíos y avisa de casos que esperan.
 */
class WatchCrmInboxCommand extends Command
{
    protected $signature = 'crm-inbox:watch';

    protected $description = 'Reintenta envíos recientes por WhatsApp y avisa de casos demorados, sin atender o con recordatorio vencido';

    public function handle(): int
    {
        if (! Schema::hasTable('crm_handoff_envelopes') || ! Schema::hasColumn('crm_handoff_envelopes', 'sla_alerted_at')) {
            $this->warn('Atención WhatsApp aún no tiene sus columnas. Corre las migraciones del CRM.');

            return self::SUCCESS;
        }

        $steps = [
            'reenviados' => fn (): int => CrmInboxWatch::retryFailed(),
            'sin tomar' => fn (): int => CrmInboxWatch::alertLate(),
            'sin respuesta' => fn (): int => array_sum(CrmInboxWatch::alertUnanswered()),
            'recordatorios' => fn (): int => CrmInboxWatch::dueFollowUps(),
        ];
        $summary = [];

        foreach ($steps as $label => $step) {
            try {
                $summary[] = $label.': '.$step();
            } catch (Throwable $exception) {
                $summary[] = $label.': error';

                Log::error('CRM vigilancia: un paso falló', [
                    'paso' => $label,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info(implode(' · ', $summary));

        return self::SUCCESS;
    }
}
