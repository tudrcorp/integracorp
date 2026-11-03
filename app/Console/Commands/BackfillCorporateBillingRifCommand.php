<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AffiliationCorporates\CorporateBillingDocumentBackfill;
use Illuminate\Console\Command;

/**
 * Corrige el documento de ventas y cuotas corporativas para que sea el RIF de la empresa.
 * Sin --apply solo muestra lo que cambiaría.
 */
class BackfillCorporateBillingRifCommand extends Command
{
    protected $signature = 'corporates:backfill-billing-rif
        {--apply : Escribe los cambios; sin esta opción solo simula}
        {--actor= : Nombre de quien autoriza, para la traza}';

    protected $description = 'Pone el RIF de la empresa en ventas y cuotas corporativas que guardaron otro documento';

    public function handle(): int
    {
        $plan = CorporateBillingDocumentBackfill::plan();
        $rows = [];

        foreach (['collections' => 'Cuota', 'sales' => 'Venta'] as $key => $label) {
            foreach ($plan[$key] as $row) {
                $rows[] = [$label, $row['code'], $row['invoice'], $row['before'] === '' ? '(vacío)' : $row['before'], $row['after']];
            }
        }

        if ($rows === []) {
            $this->info('No hay ventas ni cuotas corporativas por corregir.');

            return self::SUCCESS;
        }

        $this->table(['Tipo', 'Afiliación', 'Número', 'Documento actual', 'RIF'], $rows);
        $this->line(count($plan['collections']).' cuotas y '.count($plan['sales']).' ventas.');

        if (! $this->option('apply')) {
            $this->warn('Simulación: no se guardó nada. Repita con --apply para aplicar.');

            return self::SUCCESS;
        }

        $actor = trim((string) $this->option('actor'));

        if ($actor === '') {
            $this->error('Indique --actor con el nombre de quien autoriza el cambio.');

            return self::FAILURE;
        }

        $result = CorporateBillingDocumentBackfill::apply($actor);
        $this->info("Aplicado. Lote {$result['batch_uuid']}: {$result['collections']} cuotas y {$result['sales']} ventas.");

        return self::SUCCESS;
    }
}
