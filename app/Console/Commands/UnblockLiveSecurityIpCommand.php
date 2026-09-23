<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SecurityIpBlock;
use App\Support\LivePresence\IpBlockList;
use Illuminate\Console\Command;

/**
 * Salida de emergencia: saca una IP de la lista negra desde la consola, por
 * ejemplo si se bloqueó por error la IP de la oficina y nadie puede entrar.
 */
class UnblockLiveSecurityIpCommand extends Command
{
    protected $signature = 'live-presence:ip-unblock {ip : IP (o rango) a sacar de la lista negra} {--reason= : Motivo que queda en la auditoría}';

    protected $description = 'Saca una IP de la lista negra del monitor en vivo';

    public function handle(): int
    {
        $ip = trim((string) $this->argument('ip'));
        $blocks = SecurityIpBlock::query()->where('ip', $ip)->active()->get();

        if ($blocks->isEmpty()) {
            $this->warn('La IP '.$ip.' no tiene bloqueos vigentes.');
            IpBlockList::refresh();

            return self::SUCCESS;
        }

        $reason = (string) ($this->option('reason') ?: 'Levantado desde la consola.');

        foreach ($blocks as $block) {
            IpBlockList::lift($block, $reason);
        }

        $this->info('Listo: '.$ip.' ya puede entrar al sistema.');

        return self::SUCCESS;
    }
}
