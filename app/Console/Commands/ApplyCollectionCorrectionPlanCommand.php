<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Collections\CollectionCorrectionPlan;
use Illuminate\Console\Command;

/**
 * Aplica (o simula, o revierte) un plan de corrección de cuotas corporativas.
 * Sin --apply solo muestra qué cambiaría; nunca escribe si algo no coincide con lo esperado.
 */
class ApplyCollectionCorrectionPlanCommand extends Command
{
    protected $signature = 'collections:apply-correction
        {plan : Ruta del plan JSON (p. ej. database/corrections/2026_11_03_casalbeach_poblacion.json)}
        {--apply : Escribe los cambios; sin esta opción solo simula}
        {--revert : Aplica el plan al revés (deshace una corrección ya aplicada)}
        {--actor= : Nombre de quien autoriza, para la traza}';

    protected $description = 'Corrige cuotas y la afiliación corporativa según un plan aprobado, con verificación previa y traza';

    public function handle(): int
    {
        $path = base_path((string) $this->argument('plan'));

        if (! is_file($path)) {
            $this->error("No existe el plan: {$path}");

            return self::FAILURE;
        }

        $plan = json_decode((string) file_get_contents($path), true);

        if (! is_array($plan)) {
            $this->error('El plan no es un JSON válido.');

            return self::FAILURE;
        }

        $revert = (bool) $this->option('revert');

        try {
            $inspection = CollectionCorrectionPlan::inspect($plan, $revert);
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Registro', 'Campo', 'Actual', 'Esperado', 'Nuevo', 'Coincide'], array_map(fn (array $row): array => [
            $row['target'], $row['column'], $row['current'], $row['expected'], $row['new'], $row['done'] ? 'ya aplicado' : ($row['ok'] ? 'sí' : 'NO'),
        ], $inspection['rows']));

        if ($inspection['status'] === 'applied') {
            $this->info('El plan ya está aplicado. No se cambia nada.');

            return self::SUCCESS;
        }

        if ($inspection['status'] === 'mismatch') {
            $this->error('Hay datos que no coinciden con lo esperado. No se aplicó nada.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->warn('Simulación: no se guardó nada. Repita con --apply para aplicar.');

            return self::SUCCESS;
        }

        $actor = trim((string) $this->option('actor'));

        if ($actor === '') {
            $this->error('Indique --actor con el nombre de quien autoriza el cambio.');

            return self::FAILURE;
        }

        $result = CollectionCorrectionPlan::apply($plan, $actor, $revert);

        $this->info("Aplicado. Lote {$result['batch_uuid']}: {$result['collections']} cuotas".($result['affiliation'] ? ' y la afiliación.' : '.'));

        return self::SUCCESS;
    }
}
