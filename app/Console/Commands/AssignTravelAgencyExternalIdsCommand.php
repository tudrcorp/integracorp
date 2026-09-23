<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\TravelAgencies\TravelAgencyExternalIdAssigner;
use Illuminate\Console\Command;

/**
 * Carga id_agencia e id_de_agente en agencias de viaje ya existentes.
 * Sin --apply no escribe nada.
 */
class AssignTravelAgencyExternalIdsCommand extends Command
{
    protected $signature = 'travel-agencies:assign-external-ids {--apply : Escribe los identificadores. Sin esta opción solo muestra el plan}';

    protected $description = 'Asigna el ID de agencia y el ID de agente del listado externo, sin crear ni borrar agencias';

    public function handle(): int
    {
        $persist = (bool) $this->option('apply');
        $plan = TravelAgencyExternalIdAssigner::apply($persist);

        if (! $plan['ready']) {
            $this->error('Faltan las columnas id_agencia e id_de_agente. Primero corre la migración de agencias de viaje.');

            return self::FAILURE;
        }

        if ($persist) {
            $this->info('Aplicado. Solo se escribieron los identificadores y, donde correspondía, el nombre del Excel.');
        } else {
            $this->warn('Simulación: no se escribió nada. Para guardar, corre el comando con --apply.');
        }

        $this->newLine();
        $this->line('Por actualizar: '.$plan['updated']);
        $this->line('Nombres a reemplazar: '.$plan['renamed']);
        $this->line('Ya estaban iguales: '.$plan['unchanged']);
        $this->line('Sin agencia en la tabla: '.count($plan['skipped']));
        $this->line('Conflictos, sin tocar: '.count($plan['conflicts']));

        if ($plan['changes'] !== []) {
            $this->newLine();
            $this->table(
                ['ID interno', 'Nombre actual', 'Nombre nuevo', 'ID agencia', 'ID de agente'],
                array_map(fn (array $change): array => [
                    $change['id'],
                    $change['name'],
                    $change['new_name'] ?? '—',
                    $change['id_agencia'],
                    $change['id_de_agente'],
                ], $plan['changes']),
            );
        }

        if ($plan['conflicts'] !== []) {
            $this->newLine();
            $this->warn('No se modificaron estas agencias:');

            foreach ($plan['conflicts'] as $conflict) {
                $this->line(' - '.$conflict);
            }
        }

        if ($plan['skipped'] !== []) {
            $this->newLine();
            $this->line('Estas del listado no están en la tabla y no se crearon:');

            foreach ($plan['skipped'] as $name) {
                $this->line(' - '.$name);
            }
        }

        return self::SUCCESS;
    }
}
